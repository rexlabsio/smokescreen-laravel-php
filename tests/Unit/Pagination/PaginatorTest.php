<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Rexlabs\Laravel\Smokescreen\Pagination\Paginator;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;

class PaginatorTest extends TestCase
{
    /**
     * @dataProvider currentPageProvider
     */
    public function test_can_get_current_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getCurrentPage());
    }

    public function currentPageProvider()
    {
        return [
            [$this->createPaginator(), 1],
            [$this->createPaginator(30, 15, 2), 2],
        ];
    }

    /**
     * @dataProvider lastPageProvider
     */
    public function test_can_get_last_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getLastPage());
    }

    public function lastPageProvider()
    {
        return [
            [$this->createPaginator(80, 10), 8],
            [$this->createPaginator(80, 20), 4],
            [$this->createPaginator(100, 30), 4],
            [$this->createPaginator(0, 30), 1],
        ];
    }

    /**
     * @dataProvider countProvider
     */
    public function test_can_get_count(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getCount());
    }

    public function countProvider()
    {
        return [
            [$this->createPaginator(100), 100],
            [$this->createPaginator(99, 20), 99],
            [$this->createPaginator(30, 30), 30],
            [$this->createPaginator(0, 30), 0],
        ];
    }

    /**
     * @dataProvider perPageProvider
     */
    public function test_can_get_per_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getPerPage());
    }

    public function perPageProvider()
    {
        return [
            [$this->createPaginator(100, 20), 20],
            [$this->createPaginator(0, 100), 100],
            [$this->createPaginator(0, 1), 1],
            [$this->createPaginator(20, 15), 15],
        ];
    }

    /**
     * @dataProvider totalProvider
     */
    public function test_can_get_total(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getTotal());
    }

    public function totalProvider()
    {
        return [
            [$this->createPaginator(100), 100],
            [$this->createPaginator(50), 50],
            [$this->createPaginator(0), 0],
        ];
    }

    public function test_can_get_url()
    {
        $paginator = $this->createPaginator(100, 20, 2);
        $this->assertEquals('/?page=1', $paginator->getUrl(1));
        $this->assertEquals('/?page=100', $paginator->getUrl(100));
        $this->assertEquals('/?page=1', $paginator->getUrl(0));
    }

    public function test_can_laravel_paginator()
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Pagination\LengthAwarePaginator::class,
            $this->createPaginator()->getPaginator()
        );
    }

    protected function createPaginator(int $numItems = 50, int $perPage = 15, $currentPage = null): Paginator
    {
        $items = array_fill(0, $numItems, 'item');

        return new Paginator(new LengthAwarePaginator($items, \count($items), $perPage, $currentPage));
    }
}
