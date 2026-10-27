<?php

declare(strict_types=1);

namespace LowWidontTests;

final class PackagingTest extends TestCase
{
    /**
     * Read addon metadata from the shipped JSON and PHP setup file.
     *
     * @return void
     */
    public function testSetupMatchesTheShippedMetadata(): void
    {
        $root = dirname(__DIR__, 3);
        $json = json_decode(file_get_contents($root . '/low_widont/addon.json'), true);
        $setup = require $root . '/low_widont/addon.setup.php';
        foreach (['name', 'description', 'version', 'namespace'] as $key) {
            $this->assertSame($json[$key], $setup[$key]);
        }
        $this->assertFalse($setup['settings_exist']);
        $this->assertSame('EEHarbor', $setup['author']);
        $this->assertSame('http://eeharbor.com/low_widont/documentation', $setup['docs_url']);
    }

    /**
     * Render the add-on license view with modern and legacy contexts.
     *
     * @dataProvider viewContexts
     * @param int $version
     * @param string $key
     * @param bool $ignored
     * @return void
     */
    public function testLicenseViews($version, $key, $ignored): void
    {
        $root = dirname(__DIR__, 3);
        Environment::$services['CP/Alert'] = $this->getMockBuilder(\stdClass::class)->addMethods(['getAllInlines'])->getMock();
        Environment::$services['CP/Alert']->method('getAllInlines')->willReturn('<div>Saved license</div>');
        $paths = [$root . '/low_widont/views/license.php'];
        foreach ($paths as $path) {
            $html = $this->render($path, $version, $key, $ignored);
            $expectedKey = strpos($key, 'ignore-site-') !== false ? '' : htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $this->assertStringContainsString('name="license_key" value="' . $expectedKey . '"', $html);
            $this->assertStringContainsString('class="box ee' . $version, $html);
            $this->assertStringContainsString('<form action="save?site=1&amp;return=license"', $html);
            $this->assertSame($version > 2, strpos($html, 'Saved license') !== false);
            $this->assertSame($ignored, strpos($html, 'checked="checked"') !== false);
            $this->assertStringContainsString('Save License Key', $html);
        }
    }

    /**
     * Supply supported EE versions, ignore sentinels and characters needing escaping.
     *
     * @return array
     */
    public function viewContexts(): array
    {
        return [[2, 'key-123', false], [3, 'ignore-site-1', true], [6, 'key-"<&', false], [7, '', true]];
    }

    /**
     * Render a view with explicit caller variables and restore output buffering.
     *
     * @param string $path
     * @param int $ee_ver
     * @param string $license_key
     * @param bool $ignore_site
     * @return string
     */
    private function render($path, $ee_ver, $license_key, $ignore_site)
    {
        $action_url = 'save?site=1&return=license';
        ob_start();
        try {
            include $path;
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
