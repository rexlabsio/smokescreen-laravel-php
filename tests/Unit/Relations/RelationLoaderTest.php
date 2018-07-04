<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Relations;

use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;
use Rexlabs\Smokescreen\Resource\Collection;

class RelationLoaderTest extends TestCase
{
    public function test_calls_load_method_on_eloquent_collection()
    {
        $stub = $this->createMock(\Illuminate\Database\Eloquent\Collection::class);
        $stub->expects($this->once())
            ->method('load')
            ->with($this->equalTo(['users', 'boo']));

        $loader = new RelationLoader();
        $loader->load(new Collection($stub, $this->createTransformer()), ['users', 'boo']);
    }

    protected function createTransformer(): AbstractTransformer
    {
        return new class() extends AbstractTransformer {
            protected $includes = [
                'users'    => 'relation',
                'comments' => 'relation:boo',
            ];

            public function transform()
            {
                return [];
            }
        };
    }
}
