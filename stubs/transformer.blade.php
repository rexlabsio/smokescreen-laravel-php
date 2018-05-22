<\?php

namespace {{ $transformerNamespace }};

use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;

/**
 * { $modelName }} transformer.
 *
 */
class {{ $transformerName }} extends AbstractTransformer
{
    /**
     * The list of available includes.
     *
     * @var array
     */
    protected $includes = [
@forelse ($includes as $include => $definition)
@if ($definition)
        '{{ $include }}' => '{{ $definition }}',
@else
        '{{ $include }}',
@endif
@empty
        //
@endforelse
    ];

    /**
    * Declare the available properties.
    *
    * @var array
    */
    protected $props = [
@forelse ($properties as $property => $definition)
    @if ($definition)
        '{{ $property }}' => '{{ $definition }}',
    @else
        '{{ $property }}',
    @endif
@empty
        //
@endforelse
    ];

    /**
     * The default properties to return.
     * When empty, all the declared properties will be returned.
     *
     * @var array
     */
    protected $defaultProps = [
@forelse ($defaultProperties as $property)
        '{{ $property }}',
@empty
        //
@endforelse
];
    ];
}
