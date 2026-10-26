<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Database.php';
require_once dirname(__DIR__, 2) . '/support/Schema.php';
require_once dirname(__DIR__, 2) . '/support/Updater.php';

final class UpdaterTest extends TestCase
{
    /**
     * Supply independent install/license records and footer collaborators.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        StaticCache::put('app_settings', ['version' => '4.1.1']);
        $database = new Database(['eeharbor_licenses' => [], 'modules' => [], 'module_member_roles' => [], 'module_member_groups' => [], 'actions' => [], 'extensions' => [], 'menu_items' => []]);
        $database->columns['eeharbor_licenses'] = ['site_id', 'addon', 'license_key', 'ignore_site'];
        Environment::$ee->db = $database;
        Environment::$ee->dbforge = new Schema($database);
        Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['dbforge'])->getMock();
        $config = $this->getMockBuilder(\stdClass::class)->addMethods(['item'])->getMock();
        $values = ['site_id' => 1, 'base_url' => 'https://example.test', 'site_license_key' => false];
        $config->method('item')->willReturnCallback(static function ($key) use ($values) { return $values[$key] ?? false; });
        Environment::$ee->config = $config;
        $cp = $this->getMockBuilder(\stdClass::class)->addMethods(['get_foot', 'add_to_foot'])->getMock();
        $cp->method('get_foot')->willReturn([]);
        Environment::$ee->cp = $cp;
        Environment::$ee->functions = $this->getMockBuilder(\stdClass::class)->addMethods(['clear_caching'])->getMock();
    }

    /**
     * Initialize the real updater and register a module with exact install metadata.
     *
     * @return void
     */
    public function testInstallsModuleAndClearsDatabaseCache(): void
    {
        $updater = new \Low_widont_fixture_upd();
        $this->assertSame('Low_widont_fixture', $updater->module_name);
        $this->assertSame('4.1.1', $updater->version);
        Environment::$ee->functions->expects($this->once())->method('clear_caching')->with('db');
        $this->assertTrue($updater->install());
        $this->assertSame([['module_name' => 'Low_widont_fixture', 'module_version' => '4.1.1', 'has_cp_backend' => 'y', 'has_publish_fields' => 'n']], Environment::$ee->db->tables['modules']);
    }

    /**
     * Leave native modules untouched for an extension-only addon.
     *
     * @return void
     */
    public function testExtensionOnlyInstallDoesNotWriteModuleRecords(): void
    {
        $updater = new \Low_widont_fixture_upd();
        $updater->ext_settings = 'y';
        Environment::$ee->functions->expects($this->never())->method('clear_caching');
        $this->assertTrue($updater->install());
        $this->assertSame([], Environment::$ee->db->tables['modules']);
    }

    /**
     * Avoid an update when the installed version is absent or already current.
     *
     * @dataProvider unchangedVersions
     * @param string $installed
     * @return void
     */
    public function testNoUpdateNeeded($installed): void
    {
        $updater = new \Low_widont_fixture_upd();
        $this->assertFalse($updater->update($installed));
    }

    /**
     * Supply an absent and an exactly current installed version.
     *
     * @return array
     */
    public function unchangedVersions(): array
    {
        return [[''], ['4.1.1']];
    }

    /**
     * Remove only the target module's dependent records in safe order.
     *
     * @return void
     */
    public function testUninstallScopesEveryTableAndRetainsOtherModules(): void
    {
        Environment::$ee->db->tables['modules'] = [['module_id' => 5, 'module_name' => 'Low_widont_fixture'], ['module_id' => 6, 'module_name' => 'Other']];
        foreach (['module_member_roles', 'module_member_groups'] as $table) {
            Environment::$ee->db->tables[$table] = [['module_id' => 5], ['module_id' => 6]];
        }
        Environment::$ee->db->tables['actions'] = [['class' => 'Low_widont_fixture'], ['class' => 'Other']];
        Environment::$ee->db->tables['extensions'] = [['class' => 'Low_widont_fixture_ext'], ['class' => 'Other_ext']];
        Environment::$ee->db->tables['menu_items'] = [['data' => 'Low_widont_fixture_ext'], ['data' => 'Other_ext']];
        $updater = new \Low_widont_fixture_upd();
        $before = count(Environment::$ee->db->calls);
        $this->assertTrue($updater->uninstall());
        $calls = array_slice(Environment::$ee->db->calls, $before);
        $deletions = array_values(array_filter($calls, static function ($call) { return $call[0] === 'delete'; }));
        $this->assertSame(['module_member_roles', 'modules', 'actions', 'extensions', 'menu_items'], array_column($deletions, 1));
        $this->assertSame([['module_id' => 6, 'module_name' => 'Other']], Environment::$ee->db->tables['modules']);
        $this->assertSame([['module_id' => 6]], Environment::$ee->db->tables['module_member_roles']);
        $this->assertSame([['class' => 'Other']], Environment::$ee->db->tables['actions']);
        $this->assertSame([['class' => 'Other_ext']], Environment::$ee->db->tables['extensions']);
        $this->assertSame([['data' => 'Other_ext']], Environment::$ee->db->tables['menu_items']);
    }

    /**
     * Support plugin-only packages whose optional extension file is absent.
     *
     * @group regression
     * @return void
     */
    public function testUpdateWithoutAnExtensionFileIsDiagnosticFree(): void
    {
        $this->assertTrue((new \Low_widont_fixture_upd())->update('4.0.0'));
    }
}
