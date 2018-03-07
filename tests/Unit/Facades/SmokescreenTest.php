<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Facades;

use Rexlabs\Laravel\Smokescreen\Facades\Smokescreen;
use Rexlabs\Laravel\Smokescreen\Smokescreen as LaravelSmokescreen;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Smokescreen\Serializer\DefaultSerializer;
use Rexlabs\Smokescreen\Serializer\SerializerInterface;

class SmokescreenTest extends TestCase
{
    /** @test */
    public function it_returns_an_instance()
    {
        $this->assertInstanceOf(LaravelSmokescreen::class, Smokescreen::transform([]));
    }

    /** @test */
    public function can_serialize_with_default_serializer()
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Bob',
            ],
            [
                'id' => 2,
                'name' => 'Walter',
            ]
        ];


        $result = Smokescreen::transform($data)->toArray();
        $this->assertEquals([
            'data' => $data,
        ], $result);
    }


    /** @test */
    public function can_override_serializer_via_config()
    {
        $serializer = $this->createSerializer();
        $this->app['config']->set('smokescreen.default_serializer', \get_class($serializer));
        $data = [
          [
              'id' => 1,
              'name' => 'Bob',
          ],
          [
              'id' => 2,
              'name' => 'Walter',
          ]
        ];


        $result = Smokescreen::transform($data)->serializeWith($serializer)->toArray();
        $this->assertEquals([
            'custom_serialize' => $data,
        ], $result);
    }

    protected function createSerializer(): SerializerInterface
    {
        return new class extends DefaultSerializer
        {
            public function collection($resourceKey, array $data): array
            {
                return ['custom_serialize' => $data];
            }
        };
    }

}