<?php
namespace RexSoftware\Laravel\Smokescreen\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use RexSoftware\Laravel\Smokescreen\Resources\CollectionResource;
use RexSoftware\Laravel\Smokescreen\Resources\ItemResource;
use RexSoftware\Laravel\Smokescreen\Smokescreen;
use RexSoftware\Laravel\Smokescreen\Tests\Stubs\EloquentModelStub;

class SmokescreenTest extends TestCase
{
    /** @test */
    public function can_infer_resource_type_of_declared_item_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements ItemResource {};
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_declared_collection_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements CollectionResource {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_model()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends Model {};
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_array()
    {
        $smokescreen = Smokescreen::make();

        // Associative array
        $data = [ 'name' => 'Bob', 'age' => 21 ];
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );

        // Sequential array
        $data = [ 'item1', 'item2', 'item3' ];
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_plain_object()
    {
        $smokescreen = Smokescreen::make();

        $data = new class {};
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_illuminate_collection()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends Collection {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_eloquent_collection()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends \Illuminate\Database\Eloquent\Collection {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    /** @test */
    public function can_infer_resource_type_of_paginator()
    {
        $smokescreen = Smokescreen::make();

        $data = new class([], 0, 15) extends LengthAwarePaginator {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

}