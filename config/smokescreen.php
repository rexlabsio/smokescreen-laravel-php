<?php

return [
    // Set the default namespace for resolving transformers when
    // they are not explicitly provided.
    'transformer_namespace' => 'App\Transformers',

    // Set the transformer name when mapping models to transformers.
    'transformer_name' => '{ModelName}Transformer',

    // Set the directory where your application models can be found.
    'models_directory' => 'app/',

    // Override the default serializer to be used.
    // If not specified - the Smokescreen DefaultSerializer will be used.
    'default_serializer' => null,

    // Set the default request parameter key which is parsed for
    // the list of includes.
    'include_key' => 'include',
];
