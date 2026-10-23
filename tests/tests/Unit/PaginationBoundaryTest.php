<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

require_once dirname(__DIR__, 2) . '/support/ModelQuery.php';
require_once dirname(__DIR__, 2) . '/support/PaginatingMcp.php';

final class PaginationBoundaryTest extends TestCase
{
    /**
     * Supply missing, falsey, numeric-string and fractional query input values.
     *
     * @return array
     */
    public function pages(): array
    {
        return [[false, 1, null], ['0', 1, ''], [0, 1, '/custom'], ['2', 2, null], ['2.8', 2, '/custom']];
    }

    /**
     * Preserve the original query and render controls with the actual selected URL.
     *
     * @dataProvider pages
     * @param mixed $inputPage
     * @param int $expectedPage
     * @param string|null $url
     * @return void
     */
    public function testPaginationBoundaryInputs($inputPage, $expectedPage, $url): void
    {
        $this->assertPagination($inputPage, $expectedPage, $url);
    }

    /**
     * Keep negative user page values from producing a negative query offset.
     *
     * @group regression
     * @return void
     */
    public function testNegativePageUsesTheFirstPage(): void
    {
        $this->assertPagination('-2', 1, '/custom');
    }

    /**
     * Assert controller URL fallback, total-count isolation and pagination side effects.
     *
     * @param mixed $inputPage
     * @param int $expectedPage
     * @param string|null $url
     * @return void
     */
    private function assertPagination($inputPage, $expectedPage, $url)
    {
        StaticCache::put('app_settings', ['version' => '4.1.1']);
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $input->expects($this->once())->method('get')->with('p')->willReturn($inputPage);
        Environment::$ee->input = $input;
        $urls = $this->getMockBuilder(\stdClass::class)->addMethods(['make'])->getMock();
        $urls->expects($this->once())->method('make')->with('addons/settings/low_widont/listing', [])->willReturn('/listing');
        Environment::$services['CP/URL'] = $urls;
        $pagination = $this->getMockBuilder(\stdClass::class)->addMethods(['perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable', 'render'])->getMock();
        $pagination->expects($this->once())->method('perPage')->with(10)->willReturnSelf();
        $pagination->expects($this->once())->method('currentPage')->with($expectedPage)->willReturnSelf();
        $pagination->expects($this->once())->method('displayPageLinks')->with(3)->willReturnSelf();
        $pagination->expects($this->once())->method('queryStringVariable')->with('p')->willReturnSelf();
        $pagination->expects($this->once())->method('render')->with($url ?: '/listing')->willReturn('<nav>controls</nav>');
        Environment::$services['CP/Pagination'] = $pagination;
        $query = new ModelQuery(0);
        $controller = new PaginatingMcp();
        $this->assertSame($query, $controller->listing($query, $url));
        $this->assertSame(10, $query->limit);
        $this->assertSame(10 * ($expectedPage - 1), $query->offset);
        $this->assertSame(0, $query->allCalls);
        $this->assertSame('<nav>controls</nav>', $controller->data['pagination']);
        $this->assertContains(['CP/Pagination', 0], Environment::$calls);
    }
}
