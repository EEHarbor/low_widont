<?php

declare(strict_types=1);

namespace LowWidontTests;

require_once dirname(__DIR__, 2) . '/support/Database.php';
require_once dirname(__DIR__, 2) . '/support/Facade.php';
require_once dirname(__DIR__, 2) . '/support/LegacyHelpers.php';

final class BridgeBoundaryTest extends TestCase
{
    /**
     * Supply every independently shipped modern implementation.
     *
     * @return array
     */
    public function bridges(): array
    {
        return [['EE3'], ['EE4'], ['EE5'], ['EE6']];
    }

    /**
     * Supply both cloned legacy and factory-backed modern settings implementations.
     *
     * @return array
     */
    public function settingsBridges(): array
    {
        return array_merge([['EE2']], $this->bridges());
    }

    /**
     * Construct a real bridge with an explicit supported addon settings layout.
     *
     * @param string $version
     * @param string $module
     * @return object
     */
    private function bridge($version, $module = 'low_widont')
    {
        $class = 'Low\\Widont\\FluxCapacitor\\Bridge\\' . $version;
        return new $class(['module' => $module, 'module_name' => 'Fixture']);
    }

    /**
     * Read Structure key/value rows and scope both reads and writes to the active site.
     *
     * @dataProvider settingsBridges
     * @param string $version
     * @return void
     */
    public function testStructureSettingsLayout($version): void
    {
        $database = new Database(['structure_settings' => [
            ['site_id' => 1, 'var' => 'limit', 'var_value' => '12'],
            ['site_id' => 1, 'var' => 'empty', 'var_value' => '0'],
            ['site_id' => 2, 'var' => 'limit', 'var_value' => '99'],
        ], 'fieldtypes' => []]);
        Environment::$services['db'] = static function () use ($database) { return $database->newQuery(); };
        Environment::$ee->db = $database;
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->with('site_id')->willReturn(1);
        $settings = $this->bridge($version, 'structure')->getSettings(true);
        $this->assertSame('12', $settings['limit']);
        $this->assertSame('0', $settings['empty']);
        $this->assertSame('4.1.1', $settings['version']);
    }

    /**
     * Load addon metadata when a Structure table contains no rows for the active site.
     *
     * @dataProvider settingsBridges
     * @param string $version
     * @return void
     */
    public function testStructureSettingsForAnEmptySite($version): void
    {
        $other = ['site_id' => 2, 'var' => 'limit', 'var_value' => '99'];
        $database = new Database(['structure_settings' => [$other], 'fieldtypes' => []]);
        Environment::$services['db'] = static function () use ($database) { return $database->newQuery(); };
        Environment::$ee->db = $database;
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->with('site_id')->willReturn(1);
        $settings = $this->bridge($version, 'structure')->getSettings(true);
        $this->assertSame('4.1.1', $settings['version']);
        $this->assertArrayNotHasKey('limit', $settings);
        $this->assertSame([$other], $database->tables['structure_settings']);
        $this->assertSame([], array_values(array_filter($database->calls, static function ($call) {
            return in_array($call[0], ['insert', 'update', 'delete'], true);
        })));
    }

    /**
     * Keep request settings isolated between separately identified bridge instances.
     *
     * @group regression
     * @dataProvider settingsBridges
     * @param string $version
     * @return void
     */
    public function testSettingsCacheIsScopedToTheAddon($version): void
    {
        $database = new Database(['low_widont_settings' => [['site_id' => 1, 'limit' => 5]],
            'structure_settings' => [['site_id' => 1, 'var' => 'limit', 'var_value' => 12]], 'fieldtypes' => []]);
        Environment::$services['db'] = static function () use ($database) { return $database->newQuery(); };
        Environment::$ee->db = $database;
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->with('site_id')->willReturn(1);
        $this->assertSame(5, $this->bridge($version)->getConfig('limit'));
        $this->assertSame(12, $this->bridge($version, 'structure')->getConfig('limit'));
    }

    /**
     * Load an installed addon with absent tables and an empty legacy fieldtype payload.
     *
     * @dataProvider settingsBridges
     * @param string $version
     * @return void
     */
    public function testEmptyLegacySettingsPayload($version): void
    {
        $database = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(false))]]]);
        Environment::$ee->db = $database;
        Environment::$services['db'] = static function () use ($database) { return $database->newQuery(); };
        $this->assertSame('4.1.1', $this->bridge($version)->getConfig('version'));
    }

    /**
     * Refresh stale table metadata on each owned builder before reading or writing settings.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testFreshBuildersClearStaleTableMetadata($version): void
    {
        $database = new Database(['low_widont_settings' => [['site_id' => 1, 'limit' => 5], ['site_id' => 2, 'limit' => 99]], 'fieldtypes' => []]);
        $queries = [];
        Environment::$services['db'] = static function () use ($database, &$queries) {
            $query = $database->newQuery();
            $query->data_cache['table_names'] = ['table_list_before_addon_install'];
            $queries[] = $query;
            return $query;
        };
        Environment::$ee->config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        Environment::$ee->config->method('item')->with('site_id')->willReturn(1);
        $bridge = $this->bridge($version);
        $this->assertSame(5, $bridge->getConfig('limit'));
        $bridge->setConfig('limit', 0);
        $this->assertSame([['site_id' => 1, 'limit' => 0], ['site_id' => 2, 'limit' => 99]], $database->tables['low_widont_settings']);
        $this->assertCount(2, $queries);
        $this->assertNotSame($queries[0], $queries[1]);
        foreach ($queries as $query) {
            $this->assertArrayNotHasKey('table_names', $query->data_cache);
        }
    }

    /**
     * Add and remove an unloaded facade service without conflating it with DI factories.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testUnloadedFacadeServiceLifecycle($version): void
    {
        $facade = new Facade();
        Environment::$ee = $facade;
        $bridge = $this->bridge($version);
        $bridge->overwriteEEClass('fixture', ['id' => 1]);
        $this->assertSame(['id' => 1], $facade->get('fixture'));
        $bridge->removeEEClass('fixture');
        $this->assertFalse($facade->has('fixture'));
        $facade->set('fixture', null);
        $this->assertTrue($facade->has('fixture'));
        $this->assertNull($facade->get('fixture'));
    }

    /**
     * Supply both ordinary and explicitly null loaded services.
     *
     * @return array
     */
    public function loadedServices(): array
    {
        $cases = [];
        foreach ($this->bridges() as $bridge) {
            $cases[] = [$bridge[0], ['old' => true]];
            $cases[] = [$bridge[0], null];
        }
        return $cases;
    }

    /**
     * Replace a loaded legacy service through the public overwrite helper.
     *
     * @group regression
     * @dataProvider loadedServices
     * @param string $version
     * @param mixed $previous
     * @return void
     */
    public function testLoadedFacadeServicesCanBeOverwritten($version, $previous): void
    {
        $facade = new Facade();
        $facade->set('fixture', $previous);
        Environment::$ee = $facade;
        $this->bridge($version)->overwriteEEClass('fixture', ['replacement' => true]);
        $this->assertSame(['replacement' => true], $facade->get('fixture'));
    }

    /**
     * Preserve URL schemes while collapsing repeated path slashes through the host helper.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testDoubleSlashHelper($version): void
    {
        Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['helper'])->getMock();
        Environment::$ee->load->expects($this->once())->method('helper')->with('string');
        $this->assertSame('https://example.test/path/end/', $this->bridge($version)->reduce_double_slashes('https://example.test//path///end/'));
    }

    /**
     * Handle a site or short CP route without suppressed missing-segment warnings.
     *
     * @group regression
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testShortRequestUrlIsDiagnosticFree($version): void
    {
        Environment::$ee->uri = $this->getMockBuilder(\stdClass::class)->addMethods(['uri_string'])->getMock();
        Environment::$ee->uri->method('uri_string')->willReturn('cp/addons');
        $info = $this->bridge($version)->getCurrentUrlInfo();
        $this->assertTrue($info['cp']);
        $this->assertNull($info['module']);
        $this->assertSame('index', $info['method']);
    }

    /**
     * Reject corrupt persisted fieldtype settings without suppressed unserialize diagnostics.
     *
     * @group regression
     * @dataProvider settingsBridges
     * @param string $version
     * @return void
     */
    public function testMalformedFieldtypeSettingsAreDiagnosticFree($version): void
    {
        $database = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode('broken serialized settings')]]]);
        Environment::$services['db'] = static function () use ($database) { return $database->newQuery(); };
        Environment::$ee->db = $database;
        $this->assertSame('4.1.1', $this->bridge($version)->getConfig('version'));
    }

    /**
     * Delegate EE3 security cleaning through the selected XSS service.
     *
     * @return void
     */
    public function testEe3SecurityUsesTheXssService(): void
    {
        $security = $this->getMockBuilder(\stdClass::class)->addMethods(['clean'])->getMock();
        $security->expects($this->once())->method('clean')->with(['unsafe', 0])->willReturn(['safe', 0]);
        Environment::$services['Security/XSS'] = $security;
        $this->assertSame(['safe', 0], $this->bridge('EE3')->xss_clean(['unsafe', 0]));
    }
}
