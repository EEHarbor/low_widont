<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Base\Mcp;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Database.php';
require_once dirname(__DIR__, 2) . '/support/Schema.php';
require_once dirname(__DIR__, 2) . '/support/Redirect.php';

final class McpLicenseTest extends TestCase
{
    private $posted;
    private $alerts;

    /**
     * Install license storage, terminal redirects and observable CP services.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        StaticCache::put('app_settings', ['version' => '4.1.1']);
        $database = new Database(['eeharbor_licenses' => [], 'sites' => []]);
        $database->columns['eeharbor_licenses'] = ['site_id', 'addon', 'license_key', 'ignore_site'];
        Environment::$ee->db = $database;
        Environment::$ee->dbforge = new Schema($database);
        Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['dbforge'])->getMock();
        $config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        $config->method('item')->with('site_id')->willReturn(1);
        Environment::$ee->config = $config;
        $this->posted = ['license_key' => 'ABC-123', 'ignore_site' => '0'];
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['post'])->getMock();
        $input->method('post')->willReturnCallback(function ($key, $clean) {
            $this->assertTrue($clean);
            return isset($this->posted[$key]) ? $this->posted[$key] : false;
        });
        Environment::$ee->input = $input;
        $url = $this->getMockBuilder(\stdClass::class)->addMethods(['make', 'compile'])->getMock();
        $url->method('make')->willReturnCallback(static function ($path) { return '/' . $path; });
        $url->method('compile')->willReturn('/addons/settings/low_widont/');
        Environment::$services['CP/URL'] = $url;
        Environment::$ee->view = (object) ['cp_page_title' => 'License'];
        $functions = $this->getMockBuilder(\stdClass::class)->addMethods(['redirect'])->getMock();
        $functions->method('redirect')->willReturnCallback(static function ($path) { throw new Redirect($path); });
        Environment::$ee->functions = $functions;
        $this->alerts = [];
        $methods = ['asIssue', 'asWarning', 'asSuccess', 'withTitle', 'addToBody', 'canClose', 'defer'];
        $alert = $this->getMockBuilder(\stdClass::class)->addMethods($methods)->getMock();
        foreach ($methods as $method) {
            $alert->method($method)->willReturnCallback(function (...$arguments) use ($method, $alert) {
                $this->alerts[] = array_merge([$method], $arguments);
                return $alert;
            });
        }
        $service = $this->getMockBuilder(\stdClass::class)->addMethods(['makeInline'])->getMock();
        $service->method('makeInline')->willReturn($alert);
        Environment::$services['CP/Alert'] = $service;
    }

    /**
     * Call the real action while modeling EE's terminal redirect contract.
     *
     * @return void
     */
    private function submit()
    {
        try {
            (new Mcp())->save_license();
            $this->fail('Expected a terminal control-panel redirect.');
        } catch (Redirect $redirect) {
            $this->assertSame('/addons/settings/low_widont/license', $redirect->getMessage());
        }
    }

    /**
     * Reject an array-shaped POST license value before writing or emitting PHP diagnostics.
     *
     * @group regression
     * @return void
     */
    public function testStructuredLicenseInputIsRejectedWithoutDiagnostics(): void
    {
        $this->posted['license_key'] = ['nested-key'];
        $this->submit();
        $this->assertSame([], Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertContains(['asIssue'], $this->alerts);
    }

    /**
     * Render missing, active and ignored licenses with the exact view variables.
     *
     * @dataProvider licenseRows
     * @param array $rows
     * @param string $expectedKey
     * @param mixed $expectedIgnore
     * @return void
     */
    public function testLicenseDisplay(array $rows, $expectedKey, $expectedIgnore): void
    {
        Environment::$ee->db->tables['eeharbor_licenses'] = $rows;
        $render = $this->getMockBuilder(\stdClass::class)->addMethods(['render'])->getMock();
        $variables = null;
        $render->method('render')->willReturnCallback(static function ($vars) use (&$variables) { $variables = $vars; return '<p>License</p>'; });
        $view = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $view->expects($this->once())->method('make')->with('low_widont:license')->willReturn($render);
        Environment::$services['View'] = $view;
        $result = (new Mcp())->license();
        $this->assertSame('<p>License</p>', $result['body']);
        $this->assertSame('License', $result['heading']);
        $this->assertSame($expectedKey, $variables['license_key']);
        $this->assertSame($expectedIgnore, $variables['ignore_site']);
        $this->assertSame(substr(APP_VER, 0, 1), $variables['ee_ver']);
        $this->assertSame('/addons/settings/low_widont/save_license', $variables['action_url']);
    }

    /**
     * Include no record, other sites and both stored ignore states.
     *
     * @return array
     */
    public function licenseRows(): array
    {
        return [
            [[], '', false],
            [[['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'KEY-1', 'ignore_site' => '0']], 'KEY-1', 0],
            [[['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'low-widont-ignore-site-1', 'ignore_site' => '1']], '', 1],
            [[['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'other', 'ignore_site' => '0']], '', false],
        ];
    }

    /**
     * Sanitize input, insert one row and show a success message before redirecting.
     *
     * @dataProvider acceptedKeys
     * @param string $submitted
     * @param string $expected
     * @return void
     */
    public function testSavesAcceptedLicenseKeys($submitted, $expected): void
    {
        $this->posted['license_key'] = $submitted;
        $this->submit();
        $this->assertSame([['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => $expected, 'ignore_site' => 0]], Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertSame(['asSuccess'], $this->alerts[0]);
        $this->assertContains(['withTitle', 'License Key Updated'], $this->alerts);
    }

    /**
     * Include empty, numeric-string, punctuation and the exact fifty-character bound.
     *
     * @return array
     */
    public function acceptedKeys(): array
    {
        return [['', ''], ['0', '0'], [' <ABC_1>! ', 'ABC1'], [str_repeat('a', 50), str_repeat('a', 50)]];
    }

    /**
     * Reject an oversized sanitized key before any database license write.
     *
     * @return void
     */
    public function testOversizedKeyStopsBeforeWriting(): void
    {
        $this->posted['license_key'] = str_repeat('a', 51);
        $this->submit();
        $this->assertSame([], Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertSame(['asIssue'], $this->alerts[0]);
        $this->assertContains(['withTitle', 'License Key Too Long'], $this->alerts);
    }

    /**
     * Update an existing current-site license and preserve another site's row.
     *
     * @return void
     */
    public function testUpdatesCurrentSiteLicense(): void
    {
        Environment::$ee->db->tables['eeharbor_licenses'] = [
            ['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'old', 'ignore_site' => 0],
            ['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'keep', 'ignore_site' => 0],
        ];
        $this->submit();
        $this->assertSame('ABC-123', Environment::$ee->db->tables['eeharbor_licenses'][0]['license_key']);
        $this->assertSame('keep', Environment::$ee->db->tables['eeharbor_licenses'][1]['license_key']);
    }

    /**
     * Report duplicate MSM keys with a known site label or the fallback recovery text.
     *
     * @dataProvider duplicateSites
     * @param array $sites
     * @param string $expected
     * @return void
     */
    public function testDuplicateSiteLicenseIsRejected(array $sites, $expected): void
    {
        $rows = [['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'ABC-123', 'ignore_site' => 0]];
        Environment::$ee->db->tables['eeharbor_licenses'] = $rows;
        Environment::$ee->db->tables['sites'] = $sites;
        $this->submit();
        $this->assertSame($rows, Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertSame(['asIssue'], $this->alerts[0]);
        $this->assertStringContainsString($expected, $this->alerts[1][1]);
    }

    /**
     * Supply present and removed MSM sites.
     *
     * @return array
     */
    public function duplicateSites(): array
    {
        return [[[['site_id' => 2, 'site_label' => 'Second site']], 'Second site'], [[], 'Could not locate MSM Site name']];
    }

    /**
     * Reject a matching key that belongs to a different addon.
     *
     * @return void
     */
    public function testDuplicateAddonLicenseIsRejected(): void
    {
        $rows = [['site_id' => 1, 'addon' => 'EEHarbor\\OtherAddon', 'license_key' => 'ABC-123', 'ignore_site' => 0]];
        Environment::$ee->db->tables['eeharbor_licenses'] = $rows;
        $this->submit();
        $this->assertSame($rows, Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertStringContainsString('Other Addon', $this->alerts[1][1]);
    }

    /**
     * Generate the site's ignore sentinel instead of retaining a user-entered key.
     *
     * @return void
     */
    public function testIgnoreSiteWritesTheSiteSpecificSentinel(): void
    {
        $this->posted['ignore_site'] = '1';
        $this->submit();
        $this->assertSame('low-widont-ignore-site-1', Environment::$ee->db->tables['eeharbor_licenses'][0]['license_key']);
        $this->assertSame(1, Environment::$ee->db->tables['eeharbor_licenses'][0]['ignore_site']);
    }

    /**
     * Remove an ignored-site record when the checkbox and key are cleared.
     *
     * @return void
     */
    public function testClearingIgnoreDeletesTheOldSentinel(): void
    {
        Environment::$ee->db->tables['eeharbor_licenses'] = [['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'low-widont-ignore-site-1', 'ignore_site' => 1]];
        $this->posted['license_key'] = '';
        $this->submit();
        $this->assertSame([], Environment::$ee->db->tables['eeharbor_licenses']);
        $this->assertSame(['asSuccess'], $this->alerts[0]);
    }

    /**
     * Preserve current-site identity when SQL returns its normal string ID shape.
     *
     * @group regression
     * @return void
     */
    public function testNumericStringSiteIdDoesNotBecomeAnMsmConflict(): void
    {
        Environment::$ee->db->tables['eeharbor_licenses'] = [['site_id' => '1', 'addon' => 'Low\\Widont', 'license_key' => 'ABC-123', 'ignore_site' => 0]];
        $this->submit();
        $this->assertSame(['asSuccess'], $this->alerts[0]);
    }

    /**
     * Handle the false sentinel returned by an absent legacy EE form field.
     *
     * @return void
     */
    public function testMissingPostedKeyIsDiagnosticFree(): void
    {
        unset($this->posted['license_key']);
        $this->submit();
        $this->assertSame('', Environment::$ee->db->tables['eeharbor_licenses'][0]['license_key']);
    }
}
