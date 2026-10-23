<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings;

require_once dirname(__DIR__, 2) . '/support/Database.php';

final class GlobalFieldSettingsTest extends TestCase
{
    /**
     * Read serialized legacy settings using default and explicit fieldtype names.
     *
     * @dataProvider fieldtypeNames
     * @param string|null $name
     * @param string $expected
     * @return void
     */
    public function testReadsLegacySettings($name, $expected): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => $expected, 'settings' => base64_encode(serialize(['limit' => 0, 'enabled' => false, 'nested' => ['id' => 1]]))]]]);
        $settings = new GlobalFieldSettings($name);
        $this->assertSame($expected, $settings->fieldtype_name);
        $this->assertSame(['limit' => 0, 'enabled' => false, 'nested' => ['id' => 1]], $settings->getSettings());
        $this->assertSame(0, $settings->getSetting('limit'));
        $this->assertFalse($settings->getSetting('enabled'));
        $this->assertContains(['where', 'name', $expected], Environment::$ee->db->calls);
    }

    /**
     * Include missing and falsey aliases used by the constructor's fallback.
     *
     * @return array
     */
    public function fieldtypeNames(): array
    {
        return [[null, 'low_widont'], ['', 'low_widont'], ['other_field', 'other_field']];
    }

    /**
     * Keep missing records distinct from an existing empty settings record.
     *
     * @return void
     */
    public function testMissingSettingsCannotBeUpdated(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => []]);
        $settings = new GlobalFieldSettings();
        $this->assertNull($settings->getSettings());
        $this->assertNull($settings->getSetting('limit'));
        $this->assertFalse($settings->updateSetting('limit', 5));
        $this->assertSame([], Environment::$ee->db->tables['fieldtypes']);
    }

    /**
     * Preserve unrelated settings and serialize updates through the legacy format.
     *
     * @return void
     */
    public function testUpdatesExactlyTheSelectedFieldtype(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [
            ['name' => 'low_widont', 'settings' => base64_encode(serialize(['keep' => 'value']))],
            ['name' => 'other', 'settings' => 'unchanged'],
        ]]);
        $settings = new GlobalFieldSettings();
        $this->assertTrue($settings->updateSetting('limit', null));
        $this->assertSame(['keep' => 'value', 'limit' => null], $settings->getSettings());
        $this->assertSame('unchanged', Environment::$ee->db->tables['fieldtypes'][1]['settings']);
        $this->assertSame(base64_encode(serialize(['keep' => 'value', 'limit' => null])), Environment::$ee->db->tables['fieldtypes'][0]['settings']);
    }

    /**
     * Normalize empty or malformed legacy serialization to an existing empty record.
     *
     * @dataProvider invalidSettings
     * @param string $stored
     * @return void
     */
    public function testInvalidSerializedSettingsBecomeAnEmptyRecord($stored): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => $stored]]]);
        $settings = new GlobalFieldSettings();
        $this->assertSame([], $settings->getSettings());
        $this->assertNull($settings->getSetting('missing'));
        $this->assertTrue($settings->updateSetting('limit', 5));
        $this->assertSame(['limit' => 5], $settings->getSettings());
    }

    /**
     * Supply malformed, empty and serialized false settings.
     *
     * @return array
     */
    public function invalidSettings(): array
    {
        return [[''], ['%%%'], [base64_encode(serialize(false))], [base64_encode(serialize([]))]];
    }
    /**
     * Return null for an absent named setting without an undefined-index diagnostic.
     *
     * @group regression
     * @return void
     */
    public function testMissingNamedSettingIsDiagnosticFree(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(['present' => true]))]]]);
        $this->assertNull((new GlobalFieldSettings())->getSetting('missing'));
    }

    /**
     * Reject corrupt persisted serialization without a suppressed runtime warning.
     *
     * @group regression
     * @return void
     */
    public function testMalformedPersistedSettingsAreDiagnosticFree(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode('not serialized')]]]);
        $this->assertSame([], (new GlobalFieldSettings())->getSettings());
    }

}
