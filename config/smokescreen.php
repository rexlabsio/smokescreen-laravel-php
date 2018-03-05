<?php
return [
    // Set the default namespace for resolving transformers when
    // they are not explicitly provided.
    'transformer_namespace' => 'App\Transformers',

    // Override the default serializer to be used.
    // If not specified - the Smokescreen DefaultSerializer will be used.
    'default_serializer' => null,

    // Set the default request parameter key which is parsed for
    // the list of includes.
    'include_key' => 'include',
];
