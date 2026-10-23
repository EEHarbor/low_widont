<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;
use Low\Widont\FluxCapacitor\Conduit\Version;

require_once dirname(__DIR__, 2) . '/support/Database.php';
require_once dirname(__DIR__, 2) . '/support/Schema.php';

final class VersionTest extends TestCase
{
    private $query;

    /**
     * Supply the real license-table shape and isolate licensing request state.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->query = $_GET;
        $database = new Database(['eeharbor_licenses' => [], 'modules' => []]);
        $database->columns['eeharbor_licenses'] = ['site_id', 'addon', 'license_key', 'ignore_site'];
        Environment::$ee->db = $database;
        Environment::$ee->dbforge = new Schema($database);
        Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['dbforge'])->getMock();
        $values = ['site_id' => 1, 'base_url' => 'https://example.test', 'site_license_key' => false];
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->willReturnCallback(static function ($key) use ($values) { return $values[$key] ?? false; });
        StaticCache::put('app_settings', ['version' => '4.1.1']);
    }

    /**
     * Restore the caller's query-string state after developer-mode vectors.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $_GET = $this->query;
        parent::tearDown();
    }

    /**
     * Validate an existing primary key and avoid duplicate schema work per instance.
     *
     * @return void
     */
    public function testExistingSchemaAndCachedVerification(): void
    {
        $version = new Version();
        $this->assertFalse($this->property(Version::class, 'table_corrupt')->getValue($version));
        $this->assertArrayNotHasKey('table_names', Environment::$ee->db->data_cache);
        $calls = Environment::$ee->db->calls;
        $this->assertFalse($version->verifyLicenseTable());
        $this->assertSame($calls, Environment::$ee->db->calls);
        $this->assertSame([], Environment::$ee->dbforge->calls);
    }

    /**
     * Create missing license schema with the exact key and column declarations.
     *
     * @return void
     */
    public function testCreatesMissingSchema(): void
    {
        unset(Environment::$ee->db->tables['eeharbor_licenses']);
        unset(Environment::$ee->db->columns['eeharbor_licenses']);
        Environment::$ee->load->expects($this->once())->method('dbforge');
        $version = new Version();
        $calls = Environment::$ee->dbforge->calls;
        $this->assertSame(['site_id', 'addon', 'license_key', 'ignore_site'], array_keys($calls[0][1]));
        $this->assertSame(['add_key', 'license_key', true], $calls[1]);
        $this->assertSame(['add_key', ['site_id', 'addon'], false], $calls[2]);
        $this->assertSame(['create_table', 'eeharbor_licenses'], $calls[3]);
        $this->assertFalse($this->property(Version::class, 'table_corrupt')->getValue($version));
    }

    /**
     * Upgrade older tables that predate the ignore-site flag.
     *
     * @return void
     */
    public function testAddsMissingIgnoreSiteColumn(): void
    {
        Environment::$ee->db->columns['eeharbor_licenses'] = ['site_id', 'addon', 'license_key'];
        Environment::$ee->load->expects($this->once())->method('dbforge');
        new Version();
        $calls = Environment::$ee->dbforge->calls;
        $this->assertSame('add_column', $calls[0][0]);
        $this->assertSame('eeharbor_licenses', $calls[0][1]);
        $this->assertSame(['ignore_site' => ['type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0']], $calls[0][2]);
    }

    /**
     * Keep corrupt index metadata distinct from a valid unique license-key index.
     *
     * @dataProvider indexShapes
     * @param array $indexes
     * @return void
     */
    public function testCorruptPrimaryKeyShapes(array $indexes): void
    {
        Environment::$ee->db->primaryKeys = $indexes;
        unset(Environment::$ee->db->data_cache['table_names']);
        $version = new Version();
        $this->assertTrue($this->property(Version::class, 'table_corrupt')->getValue($version));
        $this->assertTrue($version->verifyLicenseTable());
    }

    /**
     * Supply absent, duplicate, wrong-column and nonunique primary-key metadata.
     *
     * @return array
     */
    public function indexShapes(): array
    {
        return [[[]], [[['Column_name' => 'site_id', 'Non_unique' => '0']]], [[['Column_name' => 'license_key', 'Non_unique' => '1']]], [[['Column_name' => 'license_key', 'Non_unique' => '0'], ['Column_name' => 'addon', 'Non_unique' => '0']]]];
    }

    /**
     * Restrict existence queries to the current site and addon, including a cached miss.
     *
     * @return void
     */
    public function testExistenceIsScopedAndCached(): void
    {
        $version = new Version();
        Environment::$ee->db->tables['eeharbor_licenses'] = [['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'other-site']];
        $this->assertFalse($version->keyExistsForAddon());
        $calls = Environment::$ee->db->calls;
        $this->assertFalse($version->keyExistsForAddon());
        $this->assertSame($calls, Environment::$ee->db->calls);
        StaticCache::delete('keyExistsForAddon');
        Environment::$ee->db->tables['eeharbor_licenses'][] = ['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'current'];
        $this->assertTrue($version->keyExistsForAddon());
        $calls = Environment::$ee->db->calls;
        $this->assertTrue($version->keyExistsForAddon());
        $this->assertSame($calls, Environment::$ee->db->calls);
    }

    /**
     * Insert default identity and update only the current site's addon record.
     *
     * @return void
     */
    public function testSavesAndDeletesOnlyTheSelectedLicense(): void
    {
        $version = new Version();
        $version->saveLicenseKey(['license_key' => 'first', 'ignore_site' => 0]);
        $this->assertSame([['license_key' => 'first', 'ignore_site' => 0, 'site_id' => 1, 'addon' => 'Low\\Widont']], Environment::$ee->db->tables['eeharbor_licenses']);
        StaticCache::delete('keyExistsForAddon');
        Environment::$ee->db->tables['eeharbor_licenses'][] = ['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'keep'];
        $version->saveLicenseKey(['license_key' => 'second', 'site_id' => 1, 'addon' => 'Low\\Widont']);
        $this->assertSame('second', Environment::$ee->db->tables['eeharbor_licenses'][0]['license_key']);
        $this->assertSame('keep', Environment::$ee->db->tables['eeharbor_licenses'][1]['license_key']);
        $version->deleteLicenseKey();
        $this->assertSame([['site_id' => 2, 'addon' => 'Low\\Widont', 'license_key' => 'keep']], Environment::$ee->db->tables['eeharbor_licenses']);
    }

    /**
     * Supply explicit alternate identity and null fields through the defaulting guards.
     *
     * @return void
     */
    public function testExplicitAndNullIdentityDefaults(): void
    {
        $version = new Version();
        $version->saveLicenseKey(['license_key' => 'alternate', 'site_id' => 0, 'addon' => 'Other'], 'Default');
        $this->assertSame(['license_key' => 'alternate', 'site_id' => 0, 'addon' => 'Other'], Environment::$ee->db->tables['eeharbor_licenses'][0]);
        StaticCache::delete('keyExistsForAddon');
        $version->saveLicenseKey(['license_key' => 'default', 'site_id' => null, 'addon' => null]);
        $this->assertSame(['license_key' => 'default', 'site_id' => 1, 'addon' => 'Low\\Widont'], Environment::$ee->db->tables['eeharbor_licenses'][1]);
    }

    /**
     * Return safe defaults for missing licenses and sanitize stored license text.
     *
     * @return void
     */
    public function testLicenseAndIgnoreReads(): void
    {
        $version = new Version();
        $this->assertSame('', $version->getLicenseKey());
        $this->assertFalse($version->getIgnoreSite());
        Environment::$ee->db->tables['eeharbor_licenses'] = [['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => ' A_B<script>9-! ', 'ignore_site' => '1']];
        $this->assertSame('ABscript9-', $version->getLicenseKey());
        $this->assertSame('1', $version->getIgnoreSite());
    }

    /**
     * Generate the real encrypted pixel once across repeated checks, without network IO.
     *
     * @dataProvider pixelContexts
     * @param mixed $developer
     * @param array $modules
     * @param string $host
     * @return void
     */
    public function testEncryptedPixelAndFooterIdempotency($developer, array $modules, $host): void
    {
        $_GET = ['ldev' => $developer];
        Environment::$ee->db->tables['modules'] = $modules;
        $footer = ['<script>existing</script>'];
        $cp = $this->getMockBuilder(\stdClass::class)->addMethods(['get_foot', 'add_to_foot'])->getMock();
        $cp->method('get_foot')->willReturnCallback(static function () use (&$footer) { return $footer; });
        $cp->method('add_to_foot')->willReturnCallback(static function ($html) use (&$footer) { $footer[] = $html; });
        Environment::$ee->cp = $cp;
        $version = new Version();
        $this->assertNull($version->check());
        $this->assertCount(3, $footer);
        $encodedHost = '';
        foreach (str_split($host) as $character) {
            $encodedHost .= '&#' . ord($character) . ';';
        }
        $this->assertStringContainsString('background:url(' . $encodedHost . '?', $footer[1]);
        $this->assertMatchesRegularExpression('/\?[a-f0-9]{1024}/', $footer[1]);
        $this->assertStringContainsString('<script type="text/javascript">', $footer[2]);
        $this->assertTrue($version->check());
        $this->assertCount(3, $footer);
    }

    /**
     * Include production/developer pixels with and without the Pro module.
     *
     * @return array
     */
    public function pixelContexts(): array
    {
        return [[false, [], 'https://ping.eeharbor.com/update.gif'], ['1', [['module_name' => 'Pro', 'module_version' => '2.0']], 'http://localhost:2368/update.gif']];
    }

    /**
     * Restore validated corruption state when another Version instance shares the cache.
     *
     * @group regression
     * @return void
     */
    public function testCachedVerificationRestoresInstanceState(): void
    {
        new Version();
        $second = new Version();
        $this->assertFalse($this->property(Version::class, 'table_corrupt')->getValue($second));
    }

    /**
     * Honor ExpressionEngine installations that use a nondefault database prefix.
     *
     * @group regression
     * @return void
     */
    public function testSchemaQueryHonorsConfiguredDatabasePrefix(): void
    {
        Environment::$ee->db->dbprefix = 'custom_';
        new Version();
        $this->assertContains(['query', "SHOW KEYS FROM custom_eeharbor_licenses WHERE Key_name = 'PRIMARY'"], Environment::$ee->db->calls);
    }
    /**
     * Invalidate a cached existence miss after inserting a license in the same request.
     *
     * @group regression
     * @return void
     */
    public function testInsertedLicenseBecomesVisibleToExistenceChecks(): void
    {
        $version = new Version();
        $this->assertFalse($version->keyExistsForAddon());
        $version->saveLicenseKey(['license_key' => 'new-key']);
        $this->assertTrue($version->keyExistsForAddon());
    }

    /**
     * Invalidate a cached existence hit after deleting the selected license.
     *
     * @group regression
     * @return void
     */
    public function testDeletedLicenseNoLongerExistsInTheRequestCache(): void
    {
        Environment::$ee->db->tables['eeharbor_licenses'] = [['site_id' => 1, 'addon' => 'Low\\Widont', 'license_key' => 'old']];
        $version = new Version();
        $this->assertTrue($version->keyExistsForAddon());
        $version->deleteLicenseKey();
        $this->assertFalse($version->keyExistsForAddon());
    }

    /**
     * Encode URL bytes deterministically, including empty and punctuation boundaries.
     *
     * @return void
     */
    public function testSpiderSafeEncodingBoundaries(): void
    {
        $version = new Version();
        $this->assertSame('', $this->invoke($version, 'spiderSafe', ['']));
        $this->assertSame('&#97;&#38;&#48;', $this->invoke($version, 'spiderSafe', ['a&0']));
    }

}
