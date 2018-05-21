@php
echo "<?php\n";
@endphp

namespace {{ $namespace }};

use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;

/**
 * The {{ $modelName }} transformer.
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
     * The default properties to return.
     *
     * @var array
     */
    protected $defaultProps = [
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
}
