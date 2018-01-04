<?php
namespace RexSoftware\Laravel\Smokescreen\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RexSoftware\Smokescreen\Pagination\PaginatorInterface;

/**
 * Provides a bridge between Laravel's LengthAwarePaginator and Smokescreen's pagination interface.
 * Code is heavily based on `thephpleague/fractal` package.
 *
 * @package RexSoftware\Laravel\Smokescreen\Pagination
 */
class Paginator implements PaginatorInterface
{
    /**
     * The paginator instance.
     *
     * @var \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected $paginator;

    /**
     * Create a new illuminate pagination adapter.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     *
     * @return void
     */
    public function __construct(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Get the current page.
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->paginator->currentPage();
    }

    /**
     * Get the last page.
     *
     * @return int
     */
    public function getLastPage(): int
    {
        return $this->paginator->lastPage();
    }

    /**
     * Get the total.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->paginator->total();
    }

    /**
     * Get the count.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->paginator->count();
    }

    /**
     * Get the number per page.
     *
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->paginator->perPage();
    }

    /**
     * Get the url for the given page.
     *
     * @param int $page
     *
     * @return string
     */
    public function getUrl($page): string
    {
        return $this->paginator->url($page);
    }

    /**
     * Get the paginator instance.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginator(): LengthAwarePaginator
    {
        return $this->paginator;
    }
}