<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Base\Ext;
use Low\Widont\FluxCapacitor\Base\Mod;
use Low\Widont\FluxCapacitor\Base\Pi;
use Low\Widont\FluxCapacitor\Base\Tab;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Database.php';

final class BaseClassesTest extends TestCase
{
    /**
     * Seed shared addon configuration and isolated Active Record tables.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        StaticCache::put('app_settings', ['version' => '4.1.1', 'name' => 'Low Widon’t', 'description' => 'Eliminates widows', 'docs_url' => 'https://eeharbor.com/low_widont']);
        Environment::$ee->db = new Database(['extensions' => [], 'menu_items' => []]);
    }

    /**
     * Initialize plugin/module/tab/extension bases through their actual archive parents.
     *
     * @return void
     */
    public function testConstructorsUseTheConfiguredVersion(): void
    {
        foreach ([new Pi(), new Mod(), new Tab(), new Ext()] as $instance) {
            $this->assertSame('4.1.1', $instance->version);
            $this->assertInstanceOf('Low\Widont\FluxCapacitor\FluxCapacitor', $instance->flux);
        }
        $this->assertSame('Low Widon’t', (new Ext())->name);
    }

    /**
     * Preserve parameter values and use fallbacks only after optional whitespace normalization.
     *
     * @dataProvider templateParameters
     * @param mixed $value
     * @param bool $trim
     * @param mixed $expected
     * @return void
     */
    public function testModuleTemplateParameters($value, $trim, $expected): void
    {
        $template = $this->getMockBuilder(\stdClass::class)->addMethods(['fetch_param'])->getMock();
        $template->expects($this->once())->method('fetch_param')->with('limit')->willReturn($value);
        Environment::$ee->TMPL = $template;
        $this->assertSame($expected, $this->invoke(new Mod(), 'getParameter', ['limit', 'fallback', $trim]));
    }

    /**
     * Supply whitespace entities, disabled normalization and falsey values.
     *
     * @return array
     */
    public function templateParameters(): array
    {
        return [['  value&nbsp;&#32; ', true, 'value'], ['  value ', false, '  value '], ['', true, 'fallback'], ['0', true, 'fallback'], [false, false, 'fallback'], [null, false, 'fallback'], [0, false, 'fallback']];
    }

    /**
     * Use the fallback for the false value returned by a missing EE template parameter.
     *
     * @return void
     */
    public function testMissingTemplateParameterIsSafeToNormalize(): void
    {
        $template = $this->getMockBuilder(\stdClass::class)->addMethods(['fetch_param'])->getMock();
        $template->method('fetch_param')->willReturn(false);
        Environment::$ee->TMPL = $template;
        $this->assertSame('fallback', $this->invoke(new Mod(), 'getParameter', ['missing', 'fallback']));
    }

    /**
     * Convert legacy and model-backed publish data without losing valid zero identifiers.
     *
     * @dataProvider entryContexts
     * @param object|null $entry
     * @param array $params
     * @param array $expected
     * @return void
     */
    public function testTabEntryVariables($entry, array $params, array $expected): void
    {
        $tab = new Tab();
        $this->assertSame($expected, $tab->getEntryVariables($entry, $params));
        $this->assertSame([], $tab->display(5, 7));
        $this->assertSame([], $tab->publish_tabs(5));
        $this->assertTrue($tab->save($entry, $params));
        $this->assertTrue($tab->publish_data_db($params));
    }

    /**
     * Supply model precedence, legacy parameter, zero and empty publish contexts.
     *
     * @return array
     */
    public function entryContexts(): array
    {
        return [
            [(object) ['entry_id' => 7, 'channel_id' => 3, 'site_id' => 1], ['entry_id' => 99], ['entry_id' => 7, 'channel_id' => 3, 'site_id' => 1]],
            [null, ['entry_id' => 0, 'meta' => ['channel_id' => '3', 'site_id' => 2]], ['entry_id' => 0, 'channel_id' => '3', 'site_id' => 2]],
            [null, [], ['entry_id' => 0, 'channel_id' => 0, 'site_id' => 0]],
        ];
    }

    /**
     * Register hooks idempotently with exact settings, ordering, and custom arguments.
     *
     * @return void
     */
    public function testExtensionRegistrationIsIdempotent(): void
    {
        $extension = new Ext();
        $extension->settings = ['enabled' => false, 'limit' => 0];
        $this->assertTrue($extension->registerExtension('onPublish', 'publish_end', 20, 'n'));
        $this->assertTrue($extension->registerExtension('onPublish', 'publish_end', 20, 'n'));
        $this->assertCount(1, Environment::$ee->db->tables['extensions']);
        $this->assertSame([
            'class' => Ext::class, 'method' => 'onPublish', 'hook' => 'publish_end', 'settings' => serialize($extension->settings),
            'priority' => 20, 'version' => '4.1.1', 'enabled' => 'n',
        ], Environment::$ee->db->tables['extensions'][0]);
        $this->assertTrue($this->invoke($extension, 'unregisterExtension', ['onPublish', 'publish_end']));
        $this->assertSame([], Environment::$ee->db->tables['extensions']);
    }

    /**
     * Reset malformed settings, infer default hooks and update installed versions.
     *
     * @dataProvider activationHookCollections
     * @param array|\Traversable $hooks
     * @return void
     */
    public function testExtensionActivationAndAliases($hooks): void
    {
        $extension = new Ext();
        $extension->settings = null;
        $extension->hooks = $hooks;
        $this->assertTrue($extension->activate_extension());
        $this->assertSame([], $extension->settings);
        $this->assertCount(2, Environment::$ee->db->tables['extensions']);
        $this->assertTrue($extension->update_extension('1.0.0'));
        $this->assertTrue($this->invoke($extension, 'update_version'));
        $this->assertTrue($this->invoke($extension, 'register_extension', ['new_hook']));
        $this->assertTrue($this->invoke($extension, 'unregister_extension', ['new_hook']));
        $this->assertCount(2, Environment::$ee->db->tables['extensions']);
        $this->assertTrue($extension->disable_extension());
        $this->assertSame([], Environment::$ee->db->tables['extensions']);
    }

    /**
     * Supply ordinary array and native iterator hook lists with stable registration order.
     *
     * @return array
     */
    public function activationHookCollections(): array
    {
        $hooks = ['entry_saved', 'entry_deleted'];
        return [[$hooks], [new \ArrayIterator($hooks)]];
    }

    /**
     * Update only this extension's version when it declares no registration hooks.
     *
     * @dataProvider emptyHookCollections
     * @param array|\Traversable $hooks
     * @return void
     */
    public function testEmptyHooksDoNotCreateRegistrations($hooks): void
    {
        $extension = new Ext();
        $extension->hooks = $hooks;
        Environment::$ee->db->tables['extensions'] = [
            ['class' => Ext::class, 'version' => '1.0.0'],
            ['class' => 'Other_ext', 'version' => '2.0.0'],
        ];
        $this->assertTrue($extension->activateExtension());
        $this->assertSame([
            ['class' => Ext::class, 'version' => '4.1.1'],
            ['class' => 'Other_ext', 'version' => '2.0.0'],
        ], Environment::$ee->db->tables['extensions']);
        $this->assertSame(['update'], array_column(Environment::$ee->db->calls, 0));
    }

    /**
     * Supply native empty collections accepted by the public hook iteration boundary.
     *
     * @return array
     */
    public function emptyHookCollections(): array
    {
        return [[[]], [new \ArrayIterator([])]];
    }

    /**
     * Forward custom hook options through the legacy registration alias.
     *
     * @group regression
     * @return void
     */
    public function testLegacyRegistrationPreservesCustomArguments(): void
    {
        $extension = new Ext();
        $this->invoke($extension, 'register_extension', ['handler', 'custom_hook', 42, 'n']);
        $row = Environment::$ee->db->tables['extensions'][0];
        $this->assertSame('custom_hook', $row['hook']);
        $this->assertSame(42, $row['priority']);
        $this->assertSame('n', $row['enabled']);
    }

    /**
     * Remove the explicitly selected custom hook through the legacy unregister alias.
     *
     * @group regression
     * @return void
     */
    public function testLegacyUnregisterPreservesTheCustomHook(): void
    {
        $extension = new Ext();
        $extension->registerExtension('handler', 'custom_hook');
        $extension->registerExtension('handler', 'handler');
        $this->assertTrue($this->invoke($extension, 'unregister_extension', ['handler', 'custom_hook']));
        $this->assertSame(['handler'], array_column(Environment::$ee->db->tables['extensions'], 'hook'));
    }

    /**
     * Return plugin metadata from the documented static info entry point.
     *
     * @group regression
     * @return void
     */
    public function testStaticPluginInfoReturnsMetadata(): void
    {
        $this->assertIsArray(Pi::info());
    }
}
