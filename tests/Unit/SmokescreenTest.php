<?php
namespace Rexlabs\Laravel\Smokescreen\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Smokescreen;

class SmokescreenTest extends TestCase
{
    public function test_transform_on_item_sets_item_resource()
    {
        $data = new class implements ItemResource {};
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_assoc_array_sets_item_resource()
    {
        $data = [
            'name' => 'Bob',
            'age' => 21,
        ];
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_collection_sets_collection_resource()
    {
        $data = new class implements CollectionResource {};
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_sequential_array_sets_collection_resource()
    {
        $data = ['one','two','three'];
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_unknown_resource_sets_item_resource()
    {
        $data = new \stdClass();
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_item_method_sets_smokescreen_item_resource()
    {
        $data = new \stdClass();
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['item'])
            ->getMock();

        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->item($data, null);
    }

    public function test_collection_method_sets_smokescreen_collection_resource()
    {
        $data = [];
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['collection'])
            ->getMock();

        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->collection($data, null);
    }

    public function test_collection_method_with_paginator_delegates_to_paginate_method()
    {
        $data = new LengthAwarePaginator(['one'], 1, 15);;
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['paginate'])
            ->getMock();
        $stub->expects($this->once())
            ->method('paginate')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->collection($data, null);
    }


    public function test_paginate_method_sets_smokescreen_collection_resource()
    {
        $data = new LengthAwarePaginator(['one'], 1, 15);
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection');
//            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->paginate($data, null);
    }

    public function test_infer_resource_type_of_declared_item_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements ItemResource {};
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    public function test_infer_resource_type_of_declared_collection_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements CollectionResource {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    public function test_infer_resource_type_of_model()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends Model {};
        $this->assertEquals(
            Smokescreen::TYPE_ITEM_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    public function test_infer_resource_type_of_array()
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

    public function test_infer_ambiguous_resource_type_of_plain_object()
    {
        $smokescreen = Smokescreen::make();

        $data = new class {};
        $this->assertEquals(
            Smokescreen::TYPE_AMBIGUOUS_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    public function test_infer_resource_type_of_illuminate_collection()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends Collection {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

    public function test_infer_resource_type_of_eloquent_collection()
    {
        $smokescreen = Smokescreen::make();

        $data = new class extends \Illuminate\Database\Eloquent\Collection {};
        $this->assertEquals(
            Smokescreen::TYPE_COLLECTION_RESOURCE,
            $smokescreen->determineResourceType($data)
        );
    }

//    public function test_infer_resource_type_of_paginator()
//    {
//        $smokescreen = Smokescreen::make();
//
//        $data = new class([], 0, 15) extends LengthAwarePaginator {};
//        $this->assertEquals(
//            Smokescreen::TYPE_COLLECTION_RESOURCE,
//            $smokescreen->determineResourceType($data)
//        );
//    }

}