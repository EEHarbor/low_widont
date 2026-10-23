<?php

declare(strict_types=1);

namespace LowWidontTests;

require_once dirname(__DIR__, 2) . '/support/Navigation.php';

final class BridgeNavigationTest extends TestCase
{
    private $sidebar;
    private $last;
    private $breadcrumbs;

    /**
     * Install concrete Sidebar/URL contracts and request-specific URI state.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->sidebar = new Sidebar();
        $this->last = 'low_widont';
        $this->breadcrumbs = [];
        Environment::$services['CP/Sidebar'] = $this->sidebar;
        $urls = new UrlFactory();
        Environment::$services['CP/URL'] = static function ($path = null) use ($urls) { return $path === null ? $urls : $urls->make($path); };
        Environment::$ee->uri = $this->getMockBuilder(\stdClass::class)->addMethods(['segment_array'])->getMock();
        Environment::$ee->uri->method('segment_array')->willReturnCallback(function () { return ['cp', 'addons', 'settings', $this->last]; });
        Environment::$ee->view = (object) ['cp_page_title' => 'Original'];
        $cp = $this->getMockBuilder(\stdClass::class)->addMethods(['set_breadcrumb'])->getMock();
        $cp->method('set_breadcrumb')->willReturnCallback(function ($url, $label) { $this->breadcrumbs[] = [(string) $url, $label]; });
        Environment::$ee->cp = $cp;
    }

    /**
     * Supply every separately shipped modern bridge implementation.
     *
     * @return array
     */
    public function bridges(): array
    {
        return [['EE3'], ['EE4'], ['EE5'], ['EE6']];
    }

    /**
     * Instantiate an immutable bridge with the actual addon identity.
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
     * Preserve header order, index aliases, external URLs and button destinations.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testBuildsNavigationAndSelectsTheAddonIndex($version): void
    {
        $this->bridge($version)->getNav(['/' => 'Overview', 'license' => 'License', 'https://example.test/help' => 'Help'], ['index' => ['create' => 'New']]);
        $this->assertSame(['Overview', 'License', 'Help'], array_column($this->sidebar->headers, 'title'));
        $this->assertSame('/addons/settings/low_widont/index', (string) $this->sidebar->headers[0]->url);
        $this->assertSame('https://example.test/help', $this->sidebar->headers[2]->url);
        $this->assertSame('New', $this->sidebar->headers[0]->buttons[0][0]);
        $this->assertSame('/addons/settings/low_widont/create', (string) $this->sidebar->headers[0]->buttons[0][1]);
        $this->assertTrue($this->sidebar->headers[0]->active);
        $this->assertFalse($this->sidebar->headers[1]->active);
        $this->assertSame('Overview', Environment::$ee->view->cp_page_title);
        $this->assertSame([['/addons/settings/low_widont/index', 'Low\\Widont']], $this->breadcrumbs);
    }

    /**
     * Leave unknown routes and empty navigation free of active-title side effects.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testUnknownAndEmptyRoutesDoNotChangeThePageTitle($version): void
    {
        $this->last = 'unknown';
        $bridge = $this->bridge($version);
        $bridge->getNav(['license' => 'License'], [], ['unknown' => 'missing']);
        $this->assertFalse($this->sidebar->headers[0]->active);
        $bridge->getNav();
        $this->assertSame('Original', Environment::$ee->view->cp_page_title);
        $this->assertSame([], $this->breadcrumbs);
    }

    /**
     * Select an explicit license route instead of the index alias.
     *
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testExplicitRouteBecomesActive($version): void
    {
        $this->last = 'license';
        $this->bridge($version)->getNav(['index' => 'Overview', 'license' => 'License']);
        $this->assertFalse($this->sidebar->headers[0]->active);
        $this->assertTrue($this->sidebar->headers[1]->active);
        $this->assertSame('License', Environment::$ee->view->cp_page_title);
    }

    /**
     * Use the mapped label as the page title, rather than storing a Header object.
     *
     * @group regression
     * @dataProvider bridges
     * @param string $version
     * @return void
     */
    public function testActiveMapRetainsAStringPageTitle($version): void
    {
        $this->last = 'edit';
        $this->bridge($version)->getNav(['index' => 'Overview', 'license' => 'License'], [], ['edit' => 'license']);
        $this->assertTrue($this->sidebar->headers[1]->active);
        $this->assertSame('License', Environment::$ee->view->cp_page_title);
    }
}
