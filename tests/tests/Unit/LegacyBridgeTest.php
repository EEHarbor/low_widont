<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Bridge\EE2;
use Low\Widont\FluxCapacitor\Bridge\EE3;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Database.php';
require_once dirname(__DIR__, 2) . '/support/LegacyHelpers.php';

if (!defined('AMP')) {
    define('AMP', '&amp;');
    define('BASE', 'index.php?route=cp');
}

final class LegacyBridgeTest extends TestCase
{
    private $config;
    private $query;
    private $server;

    /**
     * Model legacy URI/session/config collaborators without global runtime changes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->query = $_GET;
        $this->server = $_SERVER;
        $this->config = ['cp_session_type' => 's', 'admin_session_type' => 's', 'site_id' => 1, 'theme_folder_url' => '/themes/'];
        $configuration = $this->getMockBuilder(\stdClass::class)->addMethods(['item', 'slash_item'])->getMock();
        $configuration->method('item')->willReturnCallback(function ($key) { return $this->config[$key] ?? false; });
        $configuration->method('slash_item')->willReturnCallback(function ($key) { return rtrim($this->config[$key], '/') . '/'; });
        Environment::$ee->config = $configuration;
        $session = $this->getMockBuilder(\stdClass::class)->addMethods(['userdata', 'set_flashdata'])->getMock();
        $session->cache = [];
        $session->method('userdata')->willReturnCallback(static function ($key) { return $key === 'fingerprint' ? 'fingerprint' : 'session-id'; });
        Environment::$ee->session = $session;
        Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['helper', 'library', 'view'])->getMock();
        Environment::$ee->cp = $this->getMockBuilder(\stdClass::class)->addMethods(['set_right_nav', 'add_to_foot'])->getMock();
    }

    /**
     * Restore request globals for every independently runnable test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $_GET = $this->query;
        $_SERVER = $this->server;
        parent::tearDown();
    }

    /**
     * Instantiate the actual legacy bridge with its addon identity.
     *
     * @return EE2
     */
    private function bridge()
    {
        return new EE2(['module' => 'low_widont', 'module_name' => 'Low Widon’t']);
    }

    /**
     * Append session/fingerprint directives and normalize index/module paths.
     *
     * @group regression
     * @return void
     */
    public function testLegacyAddonUrls(): void
    {
        $bridge = $this->bridge();
        $base = 'index.php?route=cp&amp;C=addons_modules&amp;M=show_module_cp&amp;module=low_widont';
        $this->assertSame($base . '&amp;S=session-id&amp;D=cp', $bridge->getBaseURL('/'));
        $this->assertSame($base . '&amp;method=license&amp;extra=1&amp;S=session-id&amp;D=cp', $bridge->getBaseURL('license', '&amp;extra=1'));
        $this->config['cp_session_type'] = 'cs';
        $this->assertStringContainsString('&amp;S=fingerprint', $bridge->getBaseURL());
        $this->config['cp_session_type'] = false;
        $this->assertSame($base . '&amp;D=cp', $bridge->getBaseURL());
        $this->assertSame($base . '&amp;D=cp&amp;method=edit&amp;entry_id=0', $bridge->moduleURL('edit', ['entry_id' => 0]));
        $this->assertSame($base . '&amp;D=cp&amp;method=index', $bridge->moduleURL('index', null));
    }

    /**
     * Translate modern CP route names into each supported legacy controller/action.
     *
     * @return void
     */
    public function testLegacyControlPanelRoutes(): void
    {
        $bridge = $this->bridge();
        $prefix = 'index.php?route=cp&amp;D=cp&amp;C=';
        $this->assertSame($prefix . 'content_edit&amp;channel_id=5', $bridge->cpURL('listing', 'ignored', ['filter_by_channel' => 5]));
        $this->assertSame($prefix . 'content_edit', $bridge->cpURL('listing'));
        foreach (['create', 'edit'] as $mode) {
            $this->assertSame($prefix . 'content_publish&amp;M=entry_form', $bridge->cpURL('publish', $mode));
        }
        $this->assertSame($prefix . 'content_publish&amp;M=other', $bridge->cpURL('publish', 'other'));
        $this->assertSame($prefix . 'members&amp;M=member_group_manager', $bridge->cpURL('members', 'groups'));
        $this->assertSame($prefix . 'members', $bridge->cpURL('members'));
        $this->assertSame($prefix . 'admin_content&amp;M=channel_add', $bridge->cpURL('channels', 'create'));
        $this->assertSame($prefix . 'admin_content', $bridge->cpURL('channels'));
        $this->assertSame($prefix . 'addons_modules', $bridge->cpURL('addons'));
        $this->assertSame($prefix . 'custom&amp;M=action&amp;id=0', $bridge->cpURL('custom', 'action', ['id' => 0]));
    }

    /**
     * Render right navigation through the legacy addon URL builder.
     *
     * @group regression
     * @return void
     */
    public function testLegacyNavigation(): void
    {
        $bridge = $this->bridge();
        $expected = ['Overview' => $bridge->getBaseURL('index'), 'Help' => 'https://example.test/help'];
        Environment::$ee->cp->expects($this->once())->method('set_right_nav')->with($expected);
        $bridge->getNav(['index' => 'Overview', 'https://example.test/help' => 'Help']);
    }

    /**
     * Delegate caller variables to the legacy view loader independently of URL building.
     *
     * @return void
     */
    public function testLegacyView(): void
    {
        $bridge = $this->bridge();
        Environment::$ee->load->expects($this->once())->method('view')->with('license', ['license_key' => 'KEY', 'ee_ver' => substr(APP_VER, 0, 1)], true)->willReturn('<view>license</view>');
        $this->assertSame('<view>license</view>', $bridge->view('license', ['license_key' => 'KEY'], true));
    }

    /**
     * Treat per_page as an offset and fall back to page/default query parameters.
     *
     * @return void
     */
    public function testLegacyPagination(): void
    {
        $bridge = $this->bridge();
        $query = [];
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $input->method('get')->willReturnCallback(static function ($key) use (&$query) { return $query[$key] ?? false; });
        Environment::$ee->input = $input;
        $this->assertSame(1, $bridge->getCurrentPage(['per_page' => 25]));
        $query = ['page' => '3'];
        $this->assertSame(3, $bridge->getCurrentPage(['per_page' => 25]));
        $query = ['per_page' => '50', 'page' => '9'];
        $this->assertEquals(3, $bridge->getCurrentPage(['per_page' => 25]));
        $this->assertSame(50, $bridge->getStartNum(['current_page' => 3, 'per_page' => 25]));
        $pagination = $this->getMockBuilder(\stdClass::class)->addMethods(['initialize', 'create_links'])->getMock();
        $options = [];
        $pagination->method('initialize')->willReturnCallback(static function ($value) use (&$options) { $options[] = $value; });
        $pagination->method('create_links')->willReturn('<nav>pages</nav>');
        Environment::$ee->pagination = $pagination;
        $this->assertSame('<nav>pages</nav>', $bridge->pagination(['current_page' => 3, 'per_page' => 25]));
        $this->assertSame('<nav>pages</nav>', $bridge->pagination(['per_page' => 10]));
        $this->assertSame([['current_page' => 3, 'per_page' => 25, 'cur_page' => 3], ['per_page' => 10]], $options);
    }

    /**
     * Preserve legacy request-cache values, including false and clear/delete behavior.
     *
     * @return void
     */
    public function testLegacyRequestCache(): void
    {
        $bridge = $this->bridge();
        $this->assertFalse($bridge->cache('get', 'missing', false, false));
        $this->assertSame(['id' => 1], $bridge->cache('set', 'entry', ['id' => 1], false));
        $this->assertSame(['id' => 1], $bridge->cache('get', 'entry', false, false));
        $this->assertNull($bridge->cache('delete', 'entry'));
        $this->assertFalse($bridge->cache('get', 'entry', false, false));
        $bridge->cache('set', 'entry', 'cached', false);
        $this->assertNull($bridge->cache('clear'));
        $this->assertArrayNotHasKey('low_widont', Environment::$ee->session->cache);
        $this->assertFalse($bridge->cache('unknown'));
    }

    /**
     * Resolve the public default-options call without diagnostics when EE supplies an offset.
     *
     * @group regression
     * @return void
     */
    public function testOffsetInputIsSafeWithDefaultPageOptions(): void
    {
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $input->method('get')->willReturnCallback(static function ($name, $clean) {
            return $name === 'per_page' ? '50' : false;
        });
        Environment::$ee->input = $input;
        $this->assertIsNumeric($this->bridge()->getCurrentPage());
    }

    /**
     * Merge and persist legacy settings through a cloned connection without losing writes.
     *
     * @return void
     */
    public function testLegacySettingsAndModuleLookup(): void
    {
        $database = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(['field_option' => false]))]], 'low_widont_settings' => [], 'modules' => [['module_name' => 'Pro']]]);
        Environment::$ee->db = $database;
        $bridge = $this->bridge();
        $this->assertSame('4.1.1', $bridge->getConfig('version'));
        $this->assertFalse($bridge->getSettings(true)['field_option']);
        $this->assertSame('4.1.1', $bridge->getSettings()->version);
        $bridge->setConfig('limit', 0);
        $this->assertSame([['site_id' => 1, 'limit' => 0]], $database->tables['low_widont_settings']);
        $bridge->setConfig('limit', 8);
        $this->assertSame(8, $bridge->getConfig('limit'));
        $this->assertTrue($bridge->module_installed('Pro'));
        $this->assertFalse($bridge->module_installed('Missing'));
        unset($database->tables['low_widont_settings']);
        $bridge->setConfig('limit', null);
        $this->assertNull($bridge->getConfig('limit'));
    }

    /**
     * Delegate legacy API, security, formatting, template and footer operations.
     *
     * @return void
     */
    public function testLegacyHostDelegation(): void
    {
        $bridge = $this->bridge();
        $api = $this->getMockBuilder(\stdClass::class)->addMethods(['instantiate'])->getMock();
        $api->expects($this->once())->method('instantiate')->with('channel_fields');
        Environment::$ee->api = $api;
        $bridge->instantiate('channel_fields');
        $bridge->overwriteEEClass('fixture', ['id' => 1]);
        $this->assertSame(['id' => 1], Environment::$ee->fixture);
        $bridge->removeEEClass('fixture');
        $this->assertSame('', Environment::$ee->fixture);
        Environment::$ee->security = $this->getMockBuilder(\stdClass::class)->addMethods(['xss_clean'])->getMock();
        Environment::$ee->security->expects($this->once())->method('xss_clean')->with(['unsafe'])->willReturn(['safe']);
        $this->assertSame(['safe'], $bridge->xss_clean(['unsafe']));
        Environment::$services['legacy:url_title'] = static function ($text) { return 'slug:' . $text; };
        Environment::$services['legacy:prep_url'] = static function ($text) { return 'prepared:' . $text; };
        $this->assertSame('slug:a title', $bridge->url_title(' A TITLE '));
        $ee3 = new EE3(['module' => 'low_widont', 'module_name' => 'Low Widon’t']);
        $this->assertSame('slug:a title', $ee3->url_title(' A TITLE '));
        $this->assertSame('prepared:example.test', $ee3->prep_url('example.test'));
        $functions = $this->getMockBuilder(\stdClass::class)->addMethods(['assign_parameters', 'assign_variables'])->getMock();
        $functions->method('assign_parameters')->with('limit="2"', ['limit' => 1])->willReturn(['limit' => '2']);
        $functions->method('assign_variables')->with('{title}')->willReturn(['title']);
        Environment::$ee->functions = $functions;
        foreach ([$bridge, $ee3] as $adapter) {
            $this->assertSame(['limit' => '2'], $adapter->assign_parameters('limit="2"', ['limit' => 1]));
            $this->assertSame(['title'], $adapter->assign_variables('{title}'));
        }
        Environment::$ee->cp->footer_item = ['existing'];
        $this->assertSame(['existing'], $bridge->getFooter());
        Environment::$ee->cp->expects($this->once())->method('add_to_foot')->with('<script type="text/javascript">alert(1);</script>');
        $bridge->javascript_to_page('alert(1);');
        $this->assertSame('/themes/third_party/low_widont/', $bridge->getAddonThemesDir());
        $this->assertSame('https://example.test/path', $bridge->reduce_double_slashes('https://example.test//path'));
        Environment::$ee->session->expects($this->once())->method('set_flashdata')->with('message_error', 'Error Details');
        $bridge->flashData('message_error', 'Error', 'Details');
        $this->assertSame(APPPATH . 'cache/', $bridge->getCachePath());
        $this->config['cache_path'] = '/custom/cache/';
        $this->assertSame('/custom/cache/', $bridge->getCachePath());
    }

    /**
     * Read method/module information from a real legacy query-string shape.
     *
     * @return void
     */
    public function testLegacyQueryStringInformation(): void
    {
        $_SERVER['QUERY_STRING'] = 'D=cp&C=addons_modules&module=low_widont&method=license';
        $_GET = ['D' => 'cp', 'module' => 'low_widont', 'method' => 'license'];
        $bridge = $this->bridge();
        $this->assertSame('license', $bridge->getCurrentUrlInfo('method'));
        $this->assertSame('low_widont', $bridge->getCurrentUrlInfo('module'));
        $this->assertSame($_SERVER['QUERY_STRING'], $bridge->getCurrentUrlInfo('full'));
        unset($_GET['method']);
        $this->assertSame('index', $bridge->getCurrentUrlInfo('method'));
        $this->assertIsArray($bridge->getCurrentUrlInfo('unknown'));
    }

    /**
     * Identify a normal legacy CP directive when no selector is requested.
     *
     * @group regression
     * @return void
     */
    public function testLegacyQueryInformationWithDefaultSelector(): void
    {
        $_SERVER['QUERY_STRING'] = 'D=cp&module=low_widont';
        $_GET = ['D' => 'cp', 'module' => 'low_widont'];
        $this->assertSame([
            'full' => $_SERVER['QUERY_STRING'],
            'cp' => true,
            'segments' => [$_SERVER['QUERY_STRING']],
            'module' => 'low_widont',
            'method' => 'index',
        ], $this->bridge()->getCurrentUrlInfo());
    }

    /**
     * Supply non-CP query strings while keeping the required module key.
     *
     * @return array
     */
    public function nonControlPanelQueries(): array
    {
        return [
            ['module=low_widont', ['module' => 'low_widont']],
            ['D=page&module=low_widont', ['D' => 'page', 'module' => 'low_widont']],
        ];
    }

    /**
     * Preserve the full default-selector description for ordinary non-CP query shapes.
     *
     * @dataProvider nonControlPanelQueries
     * @param string $query
     * @param array $parameters
     * @return void
     */
    public function testLegacyQueryInformationOutsideControlPanel($query, array $parameters): void
    {
        $_SERVER['QUERY_STRING'] = $query;
        $_GET = $parameters;
        $this->assertSame([
            'full' => $query,
            'cp' => false,
            'segments' => [$query],
            'module' => 'low_widont',
            'method' => 'index',
        ], $this->bridge()->getCurrentUrlInfo());
    }

    /**
     * Read and persist site settings when the connection has no cached table list.
     *
     * @return void
     */
    public function testLegacySettingsWithoutCachedTableNames(): void
    {
        $other = ['site_id' => 2, 'limit' => 99];
        $database = new Database(['low_widont_settings' => [['site_id' => 1, 'limit' => 5], $other], 'fieldtypes' => []]);
        $database->data_cache = [];
        Environment::$ee->db = $database;
        $bridge = $this->bridge();
        $this->assertSame(5, $bridge->getConfig('limit'));
        $bridge->setConfig('limit', 0);
        $this->assertSame([['site_id' => 1, 'limit' => 0], $other], $database->tables['low_widont_settings']);
        $this->assertSame([], $database->data_cache);
    }

    /**
     * Read site settings independently of an unfinished query on the shared legacy builder.
     *
     * @group regression
     * @return void
     */
    public function testLegacySettingsDoNotInheritPendingReadConditions(): void
    {
        $database = new Database(['low_widont_settings' => [['site_id' => 1, 'limit' => 5]],
            'fieldtypes' => [], 'unrelated' => [['id' => 9, 'limit' => 9], ['id' => 10, 'limit' => 10]]]);
        $database->data_cache = [];
        $database->where('limit', 9);
        Environment::$ee->db = $database;
        $this->assertSame(5, $this->bridge()->getConfig('limit'));
        $this->assertSame([['id' => 9, 'limit' => 9]], $database->get('unrelated')->result_array());
    }

    /**
     * Update the existing site row without inheriting or consuming another query's predicates.
     *
     * @group regression
     * @return void
     */
    public function testLegacySettingsDoNotInheritPendingWriteConditions(): void
    {
        $other = ['site_id' => 2, 'limit' => 99];
        $database = new Database(['low_widont_settings' => [['site_id' => 1, 'limit' => 5], $other],
            'fieldtypes' => [], 'unrelated' => [['id' => 9, 'limit' => 9], ['id' => 10, 'limit' => 10]]]);
        $database->data_cache = [];
        Environment::$ee->db = $database;
        $bridge = $this->bridge();
        $this->assertSame(5, $bridge->getConfig('limit'));
        $database->where('limit', 9);
        $pending = $database->calls;
        $bridge->setConfig('limit', 0);
        $this->assertSame($pending, $database->calls);
        $this->assertSame([['id' => 9, 'limit' => 9]], $database->get('unrelated')->result_array());
        $this->assertSame([['site_id' => 1, 'limit' => 0], $other], $database->tables['low_widont_settings']);
    }

    /**
     * Describe a request without optional legacy CP query keys without suppressed notices.
     *
     * @group regression
     * @return void
     */
    public function testMissingLegacyQueryKeysAreDiagnosticFree(): void
    {
        $_SERVER['QUERY_STRING'] = '';
        $_GET = [];
        $info = $this->bridge()->getCurrentUrlInfo();
        $this->assertFalse($info['cp']);
        $this->assertNull($info['module']);
        $this->assertSame('index', $info['method']);
    }
}
