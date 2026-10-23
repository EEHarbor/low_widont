<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Base\Mcp;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/ModelQuery.php';

final class McpTest extends TestCase
{
    /**
     * Supply cached real addon settings before constructing the control panel base.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        StaticCache::put('app_settings', ['version' => '4.1.1']);
    }

    /**
     * Initialize the archive MCP without optional addon navigation.
     *
     * @return void
     */
    public function testConstructorWithoutAddonNavigation(): void
    {
        $mcp = new Mcp();
        $this->assertSame('4.1.1', $mcp->version);
        $this->assertNull($mcp->nav);
    }

    /**
     * Redirect the default control panel page to license management.
     *
     * @return void
     */
    public function testIndexRedirectsToTheLicensePage(): void
    {
        $url = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $url->expects($this->once())->method('make')->with('addons/settings/low_widont/license', [])->willReturn('/license');
        Environment::$services['CP/URL'] = $url;
        $functions = $this->getMockBuilder(\stdClass::class)->addMethods(['redirect'])->getMock();
        $functions->expects($this->once())->method('redirect')->with('/license')->willReturn('redirected');
        Environment::$ee->functions = $functions;
        $this->assertSame('redirected', (new Mcp())->index());
    }

    /**
     * Paginate the caller query and expose rendered pagination without PHP diagnostics.
     *
     * @group regression
     * @return void
     */
    public function testPaginationRetainsTheModelAndStoresRenderedControls(): void
    {
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $input->expects($this->once())->method('get')->with('page')->willReturn('3');
        Environment::$ee->input = $input;
        $pagination = $this->getMockBuilder(\stdClass::class)->addMethods(['perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable', 'render'])->getMock();
        $pagination->expects($this->once())->method('perPage')->with(25)->willReturnSelf();
        $pagination->expects($this->once())->method('currentPage')->with(3)->willReturnSelf();
        $pagination->expects($this->once())->method('displayPageLinks')->with(5)->willReturnSelf();
        $pagination->expects($this->once())->method('queryStringVariable')->with('page')->willReturnSelf();
        $pagination->expects($this->once())->method('render')->with('/listing')->willReturn('<nav>pages</nav>');
        Environment::$services['CP/Pagination'] = $pagination;
        Environment::$services['CP/URL'] = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $model = new ModelQuery(100);
        $mcp = new Mcp();
        $this->assertSame($model, $mcp->paginateModel($model, '/listing'));
        $this->assertSame(25, $model->limit);
        $this->assertSame(50, $model->offset);
        $this->assertSame('<nav>pages</nav>', $mcp->data['pagination']);
    }
}
