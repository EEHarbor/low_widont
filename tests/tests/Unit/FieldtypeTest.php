<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/Fieldtype.php';

final class FieldtypeTest extends TestCase
{
    private $postedChannel;
    private $uri;

    /**
     * Supply a faithful parent-field state and request channel/URI lookups.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        StaticCache::put('app_settings', ['version' => '4.1.1']);
        $this->postedChannel = false;
        $this->uri = 'cp/publish/edit/entry/5';
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get_post'])->getMock();
        $input->method('get_post')->with('channel_id')->willReturnCallback(function () { return $this->postedChannel; });
        Environment::$ee->input = $input;
        $uri = $this->getMockBuilder(\stdClass::class)->addMethods(['uri_string'])->getMock();
        $uri->method('uri_string')->willReturnCallback(function () { return $this->uri; });
        Environment::$ee->uri = $uri;
    }

    /**
     * Initialize version/field settings and accept only declared string content types.
     *
     * @return void
     */
    public function testConstructorUpdateAndContentTypes(): void
    {
        $fieldtype = new \Low_widont_fixture_ft();
        $this->assertSame('4.1.1', $fieldtype->version);
        $this->assertSame(['name' => 'Low Widont', 'version' => '4.1.1'], $fieldtype->info);
        $this->assertInstanceOf(GlobalFieldSettings::class, $fieldtype->GlobalFieldSettings);
        $this->assertTrue($fieldtype->update());
        $this->assertTrue($fieldtype->accepts_content_type('channel'));
        foreach (['grid', 'Channel', '0', ''] as $name) {
            $this->assertFalse($fieldtype->accepts_content_type($name));
        }
    }

    /**
     * Prefer persisted channel settings, then explicit input, then the create route.
     *
     * @return void
     */
    public function testChannelSourcesAndUnsavedContent(): void
    {
        $fieldtype = new \Low_widont_fixture_ft();
        $fieldtype->_init(['settings' => ['field_channel_id' => 5]]);
        $this->postedChannel = '7';
        $this->assertSame(5, $fieldtype->getChannelId());
        $fieldtype->_init(['settings' => []]);
        $this->assertSame('7', $fieldtype->getChannelId());
        $this->postedChannel = false;
        $this->assertFalse($fieldtype->getChannelId());
        $this->uri = 'cp/publish/create/11';
        $this->assertSame(11, $fieldtype->getChannelId());
    }

    /**
     * Resolve an existing entry's channel through the native model query contract.
     *
     * @return void
     */
    public function testChannelFromAnExistingEntry(): void
    {
        $model = $this->getMockBuilder(\stdClass::class)->addMethods(['get', 'filter', 'first'])->getMock();
        $model->expects($this->once())->method('get')->with('ChannelEntry')->willReturnSelf();
        $model->expects($this->once())->method('filter')->with('entry_id', 9)->willReturnSelf();
        $model->expects($this->once())->method('first')->willReturn((object) ['channel_id' => '13']);
        Environment::$services['Model'] = $model;
        $fieldtype = new \Low_widont_fixture_ft();
        $fieldtype->_init(['content_id' => 9]);
        $this->assertSame('13', $fieldtype->getChannelId());
    }

    /**
     * Preserve setting descriptions, wide layouts and caller-defined group keys.
     *
     * @return void
     */
    public function testSettingsRowsAndLegacyAliases(): void
    {
        $fieldtype = new \Low_widont_fixture_ft();
        $this->assertNull($fieldtype->displaySettingsRow('Title', '<input>', 'Help', false));
        $this->assertNull($fieldtype->_display_settings_add_row('Wide', '<p>wide</p>', '', true));
        $this->assertSame([
            ['title' => 'Title', 'desc' => 'Help', 'wide' => false, 'fields' => [['type' => 'html', 'content' => '<input>']]],
            ['title' => 'Wide', 'desc' => '', 'wide' => true, 'fields' => [['type' => 'html', 'content' => '<p>wide</p>']]],
        ], $fieldtype->rows);
        $this->assertSame(['field_options_low_widont' => ['label' => 'field_options', 'group' => 'low_widont', 'settings' => $fieldtype->rows]], $fieldtype->displaySettingsReturn());
        $this->assertSame(['custom_options' => ['label' => 'Custom', 'group' => 'custom_group', 'settings' => $fieldtype->rows]], $fieldtype->_package_display_settings('custom_options', 'custom_group', 'Custom'));
    }

    /**
     * Handle deleted entry records without dereferencing a null model result.
     *
     * @group regression
     * @return void
     */
    public function testMissingEntryDoesNotEmitAPropertyDiagnostic(): void
    {
        $model = $this->getMockBuilder(\stdClass::class)->addMethods(['get', 'filter', 'first'])->getMock();
        $model->method('get')->willReturnSelf();
        $model->method('filter')->willReturnSelf();
        $model->method('first')->willReturn(null);
        Environment::$services['Model'] = $model;
        $fieldtype = new \Low_widont_fixture_ft();
        $fieldtype->_init(['content_id' => 9]);
        $this->assertFalse($fieldtype->getChannelId());
    }
}
