<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Database.php';

final class ModernBridgeTest extends TestCase
{
    /**
     * Supply all separately shipped modern bridge implementations.
     *
     * @return array
     */
    public function bridges(): array
    {
        return [['EE3'], ['EE4'], ['EE5'], ['EE6']];
    }

    /**
     * Instantiate one actual archive bridge with the addon identity.
     *
     * @param string $version
     * @return object
     */
    private function bridge($version)
    {
        $class = 'Low\\Widont\\FluxCapacitor\\Bridge\\' . $version;
        return new $class(['module' => 'low_widont', 'module_name' => 'Low Widon’t']);
    }

    /**
     * Preserve inherited EE version and version comparison behavior.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testVersionComparisons($version): void
    {
        $bridge = $this->bridge($version);
        $major = substr(APP_VER, 0, 1);
        $this->assertSame($major, $bridge->getEEVersion());
        $this->assertSame(APP_VER, $bridge->getEEVersion(false));
        $this->assertTrue($bridge->ver_lt('99'));
        $this->assertFalse($bridge->ver_lt('1'));
        $this->assertTrue($bridge->ver_gt('1'));
        $this->assertFalse($bridge->ver_gt('99'));
        $this->assertTrue($bridge->ver_lte($major));
        $this->assertFalse($bridge->ver_lte('1'));
        $this->assertTrue($bridge->ver_gte($major));
        $this->assertFalse($bridge->ver_gte('99'));
        foreach ([2, 3, 4, 5] as $expected) {
            $this->assertSame($major === (string) $expected, $bridge->{'is_ee' . $expected}());
        }
    }

    /**
     * Normalize addon methods and forward module URL variables exactly.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testAddonUrls($version): void
    {
        $bridge = $this->bridge($version);
        $url = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $url->expects($this->once())->method('make')->with('addons/settings/low_widont/license', ['site_id' => 2])->willReturn('module-url');
        Environment::$services['CP/URL'] = $url;
        $this->assertSame($url, $bridge->getBaseURL());
        $this->assertSame($url, $bridge->getBaseURL('/'));
        $this->assertSame($url, $bridge->getBaseURL('license', '?site=2'));
        $this->assertSame('module-url', $bridge->moduleURL('license', ['site_id' => 2]));
        $this->assertSame([
            ['CP/URL', 'addons/settings/low_widont'],
            ['CP/URL', 'addons/settings/low_widont'],
            ['CP/URL', 'addons/settings/low_widont/license?site=2'],
            ['CP/URL'],
        ], Environment::$calls);
    }

    /**
     * Route entry and channel identifiers into modern publishing paths.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testControlPanelRoutes($version): void
    {
        $bridge = $this->bridge($version);
        $calls = [];
        $url = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $url->method('make')->willReturnCallback(static function ($path, $variables) use (&$calls) {
            $calls[] = [$path, $variables];
            return $path;
        });
        Environment::$services['CP/URL'] = $url;
        $this->assertSame('publish/create/5', $bridge->cpURL('listing', 'create', ['channel_id' => 5, 'site_id' => 1]));
        $this->assertSame('publish/edit/entry/9', $bridge->cpURL('publish', 'edit', ['entry_id' => 9]));
        $this->assertSame('publish/create', $bridge->cpURL('publish', 'create'));
        $this->assertSame('members', $bridge->cpURL('members'));
        $this->assertSame('members/roles', $bridge->cpURL('members', 'roles', ['id' => 0]));
        $this->assertSame([
            ['publish/create/5', ['site_id' => 1]], ['publish/edit/entry/9', []], ['publish/create', []],
            ['members', []], ['members/roles', ['id' => 0]],
        ], $calls);
    }

    /**
     * Honor query-string page precedence and preserve fractional offset arithmetic.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testPaginationInputs($version): void
    {
        $bridge = $this->bridge($version);
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        Environment::$ee->input = $input;
        $query = [];
        $input->method('get')->willReturnCallback(static function ($key) use (&$query) { return $query[$key] ?? false; });
        $this->assertSame(1, $bridge->getCurrentPage());
        $query = ['page' => '3'];
        $this->assertSame('3', $bridge->getCurrentPage());
        $query = ['per_page' => '4', 'page' => '3'];
        $this->assertSame('4', $bridge->getCurrentPage());
        $query = ['per_page' => '0', 'page' => '0'];
        $this->assertSame(1, $bridge->getCurrentPage());
        $this->assertSame(0, $bridge->getStartNum(['current_page' => 1, 'per_page' => 25]));
        $this->assertSame(50, $bridge->getStartNum(['current_page' => 3, 'per_page' => 25]));
        $this->assertSame(6.25, $bridge->getStartNum(['current_page' => 1.5, 'per_page' => 12.5]));
    }

    /**
     * Delegate persistent and request cache operations without changing payloads.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testCacheDelegation($version): void
    {
        $bridge = $this->bridge($version);
        $cache = $this->getMockBuilder(\stdClass::class)->addMethods(['get', 'save', 'delete'])->getMock();
        $cache->expects($this->once())->method('get')->with('/low_widont/key')->willReturn(['id' => 1]);
        $cache->expects($this->once())->method('save')->with('/low_widont/key', ['id' => 2])->willReturn(true);
        $cache->expects($this->exactly(2))->method('delete')->with('/low_widont/key')->willReturn(true);
        Environment::$ee->cache = $cache;
        $session = $this->getMockBuilder(\stdClass::class)->addMethods(['cache', 'set_cache'])->getMock();
        $session->expects($this->once())->method('cache')->with('low_widont', 'key', false)->willReturn('request');
        $session->expects($this->once())->method('set_cache')->with('low_widont', 'key', null)->willReturn(null);
        Environment::$ee->session = $session;
        $this->assertSame(['id' => 1], $bridge->cache('get', 'key'));
        $this->assertTrue($bridge->cache('set', 'key', ['id' => 2]));
        $this->assertTrue($bridge->cache('delete', 'key'));
        $this->assertTrue($bridge->cache('clear', 'key'));
        $this->assertSame('request', $bridge->cache('get', 'key', false, false));
        $this->assertNull($bridge->cache('set', 'key', null, false));
        $this->assertFalse($bridge->cache('unknown', 'key'));
    }

    /**
     * Merge fieldtype, per-site database and addon settings in their documented precedence.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testSettingsMergeAndCaching($version): void
    {
        $bridge = $this->bridge($version);
        $database = new Database([
            'low_widont_settings' => [['site_id' => 1, 'limit' => 5, 'version' => 'stale']],
            'fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(['limit' => 2, 'field_option' => false]))]],
        ]);
        Environment::$services['db'] = $database;
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->with('site_id')->willReturn(1);
        $settings = $bridge->getSettings(true);
        $this->assertSame(5, $settings['limit']);
        $this->assertFalse($settings['field_option']);
        $this->assertSame('4.1.1', $settings['version']);
        $this->assertSame($settings, (array) $bridge->getSettings());
        $this->assertSame(5, $bridge->getConfig('limit'));
        $this->assertArrayNotHasKey('table_names', $database->data_cache);
        $this->assertCount(2, array_filter($database->calls, static function ($call) { return $call[0] === 'get'; }));
    }

    /**
     * Load empty database settings and persist new per-site values without clobbering a request query.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testSettingsInsertAndUpdate($version): void
    {
        $bridge = $this->bridge($version);
        $database = new Database(['low_widont_settings' => [], 'fieldtypes' => []]);
        Environment::$services['db'] = $database;
        Environment::$ee->db = new Database(['unrelated' => [['id' => 9]]]);
        Environment::$ee->db->where('id', 9);
        $pending = Environment::$ee->db->calls;
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->willReturn(1);
        $bridge->setConfig('limit', 0);
        $this->assertSame([['site_id' => 1, 'limit' => 0]], $database->tables['low_widont_settings']);
        $bridge->setConfig('limit', 10);
        $this->assertSame([['site_id' => 1, 'limit' => 10]], $database->tables['low_widont_settings']);
        $this->assertSame(10, $bridge->getConfig('limit'));
        $this->assertSame($pending, Environment::$ee->db->calls);
        unset($database->tables['low_widont_settings']);
        $bridge->setConfig('limit', null);
        $this->assertNull($bridge->getConfig('limit'));
    }

    /**
     * Normalize empty field settings while retaining JSON addon metadata.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testEmptyFieldtypeSerialization($version): void
    {
        $database = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(false))]]]);
        Environment::$services['db'] = $database;
        $this->assertSame('4.1.1', $this->bridge($version)->getConfig('version'));
    }

    /**
     * Parse complete CP URLs and use cache configuration or the EE path fallback.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testUrlInfoAndCachePath($version): void
    {
        $bridge = $this->bridge($version);
        $uri = $this->getMockBuilder(\stdClass::class)->addMethods(['uri_string'])->getMock();
        $current = 'cp/addons/settings/low_widont/license';
        $uri->method('uri_string')->willReturnCallback(static function () use (&$current) { return $current; });
        Environment::$ee->uri = $uri;
        $this->assertSame(['full' => $current, 'cp' => true, 'segments' => explode('/', $current), 'module' => 'low_widont', 'method' => 'license'], $bridge->getCurrentUrlInfo());
        $this->assertSame('license', $bridge->getCurrentUrlInfo('method'));
        $current = 'cp/addons/settings/low_widont';
        $this->assertSame('index', $bridge->getCurrentUrlInfo('method'));
        $this->assertIsArray($bridge->getCurrentUrlInfo('unknown'));
        $config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        $path = null;
        $config->method('item')->with('cache_path')->willReturnCallback(static function () use (&$path) { return $path; });
        Environment::$ee->config = $config;
        $this->assertSame(PATH_CACHE, $bridge->getCachePath());
        $path = '/custom/cache/';
        $this->assertSame($path, $bridge->getCachePath());
    }
    /**
     * Render optional pagination controls through the selected EE pagination service.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testPaginationService($version): void
    {
        $bridge = $this->bridge($version);
        $pagination = $this->getMockBuilder(\stdClass::class)->addMethods(['perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable', 'render'])->getMock();
        $pagination->expects($this->exactly(2))->method('perPage')->with(25)->willReturnSelf();
        $pagination->expects($this->exactly(2))->method('currentPage')->with(2)->willReturnSelf();
        $pagination->expects($this->once())->method('displayPageLinks')->with(7)->willReturnSelf();
        $pagination->expects($this->once())->method('queryStringVariable')->with('page')->willReturnSelf();
        $pagination->expects($this->exactly(2))->method('render')->with('/listing')->willReturn('<nav>pages</nav>');
        Environment::$services['CP/Pagination'] = $pagination;
        $options = ['total_rows' => 100, 'per_page' => 25, 'current_page' => 2, 'base_url' => '/listing'];
        $this->assertSame('<nav>pages</nav>', $bridge->pagination($options));
        $this->assertSame('<nav>pages</nav>', $bridge->pagination($options + ['page_links' => 7, 'page_query_string' => 'page']));
        $this->assertSame([['CP/Pagination', 100], ['CP/Pagination', 100]], Environment::$calls);
    }

    /**
     * Build both named and string views while preserving explicit caller variables.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testViewRendering($version): void
    {
        $bridge = $this->bridge($version);
        Environment::$ee->view = (object) ['cp_page_title' => 'License'];
        $url = $this->getMockBuilder(\stdClass::class)->addMethods(['compile'])->getMock();
        $url->method('compile')->willReturn('/addons/settings/low_widont/');
        Environment::$services['CP/URL'] = $url;
        $renderer = $this->getMockBuilder(\stdClass::class)->addMethods(['render'])->getMock();
        $vars = [];
        $renderer->method('render')->willReturnCallback(static function ($variables) use (&$vars) { $vars[] = $variables; return '<p>view</p>'; });
        $view = $this->getMockBuilder(\stdClass::class)->addMethods(['make', 'makeFromString'])->getMock();
        $view->expects($this->once())->method('make')->with('low_widont:license')->willReturn($renderer);
        $view->expects($this->once())->method('makeFromString')->with('<p>{value}</p>')->willReturn($renderer);
        Environment::$services['View'] = $view;
        $expected = ['heading' => 'License', 'breadcrumb' => ['/addons/settings/low_widont/' => 'Low Widon’t'], 'body' => '<p>view</p>'];
        $this->assertSame($expected, $bridge->view('license', ['value' => 'first']));
        $this->assertSame($expected, $bridge->view('unused', ['base_url' => '/custom', 'cp_page_title' => 'Custom'], true, '<p>{value}</p>'));
        $this->assertSame(['value' => 'first', 'ee_ver' => substr(APP_VER, 0, 1), 'base_url' => $url, 'cp_page_title' => 'License'], $vars[0]);
        $this->assertSame(['base_url' => '/custom', 'cp_page_title' => 'Custom', 'ee_ver' => substr(APP_VER, 0, 1)], $vars[1]);
    }

    /**
     * Apply alert severity, content and close overrides before deferred display.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testFlashAlerts($version): void
    {
        $bridge = $this->bridge($version);
        foreach (['message_error' => 'asIssue', 'message_failure' => 'asIssue', 'message_notice' => 'asWarning', 'message_success' => 'asSuccess', 'unknown' => 'asSuccess'] as $type => $severity) {
            $calls = [];
            $methods = ['asIssue', 'asWarning', 'asSuccess', 'withTitle', 'addToBody', 'canClose', 'cannotClose', 'defer'];
            $alert = $this->getMockBuilder(\stdClass::class)->addMethods($methods)->getMock();
            foreach ($methods as $method) {
                $alert->method($method)->willReturnCallback(static function (...$arguments) use (&$calls, $method, $alert) {
                    $calls[] = array_merge([$method], $arguments);
                    return $alert;
                });
            }
            $service = $this->getMockBuilder(\stdClass::class)->addMethods(['make', 'makeInline'])->getMock();
            $service->expects($this->once())->method($version === 'EE3' ? 'make' : 'makeInline')->with('Saved')->willReturn($alert);
            Environment::$services['CP/Alert'] = $service;
            $bridge->flashData($type, 'Saved', 'License updated', ['cannotClose', 'missingMethod']);
            $this->assertSame([[$severity], ['withTitle', 'Saved'], ['addToBody', 'License updated'], ['canClose'], ['cannotClose'], ['defer']], $calls);
        }
    }

    /**
     * Supply bridges using the modern parser and formatting service contracts.
     *
     * @return array
     */
    public function serviceBridges(): array
    {
        return [['EE4'], ['EE5'], ['EE6']];
    }

    /**
     * Pass template, security and formatting payloads to the correct EE services.
     *
     * @dataProvider serviceBridges
     * @param string $version
     * @return void
     */
    public function testServiceDelegation($version): void
    {
        $bridge = $this->bridge($version);
        $parser = $this->getMockBuilder(\stdClass::class)->addMethods(['extractVariables', 'parseTagParameters'])->getMock();
        $parser->expects($this->once())->method('extractVariables')->with('{title}')->willReturn(['title']);
        $parser->expects($this->once())->method('parseTagParameters')->with('limit="2"', ['limit' => 1])->willReturn(['limit' => '2']);
        Environment::$services['Variables/Parser'] = $parser;
        $security = $this->getMockBuilder(\stdClass::class)->addMethods(['clean'])->getMock();
        $security->expects($this->once())->method('clean')->with(['<script>x</script>'])->willReturn(['safe']);
        Environment::$services['Security/XSS'] = $security;
        $text = $this->getMockBuilder(\stdClass::class)->addMethods(['urlSlug', 'compile', 'url'])->getMock();
        $text->expects($this->once())->method('urlSlug')->willReturnSelf();
        $text->expects($this->once())->method('compile')->willReturn('a-title');
        $text->expects($this->once())->method('url')->willReturn('https://example.test');
        $format = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $formatted = [];
        $format->method('make')->willReturnCallback(static function ($type, $value) use (&$formatted, $text) { $formatted[] = [$type, $value]; return $text; });
        Environment::$services['Format'] = $format;
        $this->assertSame(['title'], $bridge->assign_variables('{title}'));
        $this->assertSame(['limit' => '2'], $bridge->assign_parameters('limit="2"', ['limit' => 1]));
        $this->assertSame(['safe'], $bridge->xss_clean(['<script>x</script>']));
        $this->assertSame('a-title', $bridge->url_title('  A TITLE  '));
        $this->assertSame('https://example.test', $bridge->prep_url('example.test'));
        $this->assertSame([['Text', 'a title'], ['Text', 'example.test']], $formatted);
    }

    /**
     * Delegate request class changes, module checks, footer scripts and theme lookup.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testLegacyCollaboratorsAndThemePath($version): void
    {
        $bridge = $this->bridge($version);
        $facade = $this->getMockBuilder(\stdClass::class)->addMethods(['set', 'remove'])->getMock();
        $facade->expects($this->once())->method('set')->with('service', ['id' => 1]);
        $facade->expects($this->once())->method('remove')->with('service');
        $facade->legacy_api = $this->getMockBuilder(\stdClass::class)->addMethods(['instantiate'])->getMock();
        $facade->legacy_api->expects($this->once())->method('instantiate')->with('channel_fields');
        $facade->addons_model = $this->getMockBuilder(\stdClass::class)->addMethods(['module_installed'])->getMock();
        $facade->addons_model->expects($this->once())->method('module_installed')->with('Pro')->willReturn(true);
        $facade->cp = $this->getMockBuilder(\stdClass::class)->addMethods(['get_foot', 'add_to_foot'])->getMock();
        $facade->cp->expects($this->once())->method('get_foot')->willReturn(['existing']);
        $facade->cp->expects($this->once())->method('add_to_foot')->with('<script type="text/javascript">alert(1);</script>');
        $facade->config = $this->getMockBuilder(\stdClass::class)->addMethods(['slash_item'])->getMock();
        $facade->config->expects($this->once())->method('slash_item')->with('theme_folder_url')->willReturn('/themes/');
        Environment::$ee = $facade;
        $bridge->instantiate('channel_fields');
        $bridge->overwriteEEClass('service', ['id' => 1]);
        $bridge->removeEEClass('service');
        $this->assertTrue($bridge->module_installed('Pro'));
        $this->assertSame(['existing'], $bridge->getFooter());
        $bridge->javascript_to_page('alert(1);');
        $this->assertSame('/themes/user/low_widont/', $bridge->getAddonThemesDir());
    }

}
