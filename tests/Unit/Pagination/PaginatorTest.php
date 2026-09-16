<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rexlabs\Laravel\Smokescreen\Pagination\Paginator;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;

class PaginatorTest extends TestCase
{
    #[DataProvider('currentPageProvider')]
    public function test_can_get_current_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getCurrentPage());
    }

    public static function currentPageProvider()
    {
        return [
            [self::createPaginator(), 1],
            [self::createPaginator(30, 15, 2), 2],
        ];
    }

    #[DataProvider('lastPageProvider')]
    public function test_can_get_last_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getLastPage());
    }

    public static function lastPageProvider()
    {
        return [
            [self::createPaginator(80, 10), 8],
            [self::createPaginator(80, 20), 4],
            [self::createPaginator(100, 30), 4],
            [self::createPaginator(0, 30), 1],
        ];
    }

    #[DataProvider('countProvider')]
    public function test_can_get_count(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getCount());
    }

    public static function countProvider()
    {
        return [
            [self::createPaginator(100), 100],
            [self::createPaginator(99, 20), 99],
            [self::createPaginator(30, 30), 30],
            [self::createPaginator(0, 30), 0],
        ];
    }

    #[DataProvider('perPageProvider')]
    public function test_can_get_per_page(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getPerPage());
    }

    public static function perPageProvider()
    {
        return [
            [self::createPaginator(100, 20), 20],
            [self::createPaginator(0, 100), 100],
            [self::createPaginator(0, 1), 1],
            [self::createPaginator(20, 15), 15],
        ];
    }

    #[DataProvider('totalProvider')]
    public function test_can_get_total(Paginator $paginator, $expected)
    {
        $this->assertEquals($expected, $paginator->getTotal());
    }

    public static function totalProvider()
    {
        return [
            [self::createPaginator(100), 100],
            [self::createPaginator(50), 50],
            [self::createPaginator(0), 0],
        ];
    }

    public function test_can_get_url()
    {
        $paginator = self::createPaginator(100, 20, 2);
        $this->assertEquals('/?page=1', $paginator->getUrl(1));
        $this->assertEquals('/?page=100', $paginator->getUrl(100));
        $this->assertEquals('/?page=1', $paginator->getUrl(0));
    }

    public function test_can_laravel_paginator()
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Pagination\LengthAwarePaginator::class,
            self::createPaginator()->getPaginator()
        );
    }

    protected static function createPaginator(int $numItems = 50, int $perPage = 15, $currentPage = null): Paginator
    {
        $items = array_fill(0, $numItems, 'item');

        return new Paginator(new LengthAwarePaginator($items, \count($items), $perPage, $currentPage));
    }
}
