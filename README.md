# Laravel Smokescreen



## Overview

Laravel Smokescreen is a package for transforming your Laravel models, and other entities.

- Transform API responses
- Transform Job and Event payloads
- Minimal boiler-plate and bootstrap
- Supports complex relationships for embedded data
- Supports eager loading of relationships
- Allows transforming different types of resources
- Can handle serializing to customisable formats

This package tightly integrates the [rexsoftware/smokescreen](https://github.com/rexlabsio/smokescreen-php) (Vanilla PHP) package with the Laravel framework, to provide the convenience and minimal boilerplate when working with Laravel applications. 

## Usage

```php
<?php
class MyController extends Controller
{
    public function index()
    {
        return Smokescreen::transform(Post::paginate());
    }
    
     public function show(Post $post)
     {
        return Smokescreen::transform($post);
     }
}
```

- `laravel-smokescreen` is bootstrapped into Laravel's app container, so you can also type-hint it to be injected into your 
controller's constructor or methods.
- Using the facade (as above) is recommended within controller methods, use type-hinting in service classes (if needed)
- You can also use it directly from the container via `app('smokescreen')` as shown above.
- Since we implement the `Responsable` interface, you can simply return smokescreen from any controller method.

## Requirements

- PHP >= 7.0
- Laravel >= 5.4

## Installation

This package is currently hosted on RexSoftware's private packagist repository. First ensure you have configured your 
`composer.json` to use this repository.

Install package

`composer require rexsoftware/laravel-smokescreen`

For Laravel 5.5:

This package will be auto-discovered, and no additional configuration is necessary.

For Laravel 5.4 and below:

Add `\RexSoftware\Laravel\Smokescreen\ServiceProvider::class` to the providers array in `config/app.php`
If you would like to use the provided facade, you can add `\RexSoftware\Laravel\Smokescreen\Facades\Smokescreen::class` to the aliases array in `config/app.php`

## Configuration

To publish the configuration file to your `app/config` folder, run the following command:

```bash
php artisan vendor:publish --provider='RexSoftware\Laravel\Smokescreen\Providers\ServiceProvider --tag=config'
```

This will create `config/smokescreen.php`:

```php
<?php
return [
    // Set the default namespace for resolving transformers when
    // they are not explicitly provided.
    'transformer_namespace' => 'App\Transformers',

    // Set the default request parameter key which is parsed for
    // the list of includes.
    'include_key' => 'include',
];
```

## API

### transform(): Set resource to be transformed

`$smokescreen->transform(mixed $resource, mixed $transformer = null);`

```php
<?php
$smokescreen->transform(Post::find(1));
$smokescreen->transform(Post::all());
$smokescreen->transform(Post::paginate());
$smokescreen->transform(Post::find(1), new SomeOtherTransformer);
```

- Smokescreen will automatically determine the type of resource being transformed.
- It will also infer the Transformer class to use if not provided.

### item(): Set single item resource to be transformed

`$smokescreen->item(mixed $item, mixed $transformer = null);`

```php
<?php
$smokescreen->item(Post::find(1));
$smokescreen->item(Post::find(1), new SomeOtherTransformer);
```

- Similar to `transform()` but only accepts a item.


### collection(): Set collection resource to be transformed

`$smokescreen->collection(mixed $collection, mixed $transformer = null);`

```php
<?php
$smokescreen->collection(Post::all());
$smokescreen->collection(Post::paginate());
$smokescreen->collection(Post::paginate(), new SomeOtherTransformer);
```

- Similar to `transform()` but only accepts a collection.

### transformWith(): Set the transformer to use on the previously set resource

`$smokescreen->transformWith(TransformerInterface|callable $transformer);`

```php
<?php
$smokescreen->transform(Post::find(1))
    ->transformWith(new SomeOtherTransformer);
```

- It's an alternative to passing the transformer directly to resource methods.

### serializeWith(): Override the serializer to be used

```php
<?php
$smokescreen->serializeWith(new MyCustomSerializer);
```

- You only need to set this if you plan to use a different serialize than the default.
- We provide `DefaultSerializer` as the default, it returns collections nested under a `"data"` node, and an item 
resource without any nesting.
- Your custom serializer should implement the `SerializerInterface` interface.

### loadRelationsVia(): Override the default Laravel relations loader

`$smokescreen->loadRelationsVia(RelationsLoaderInterface $loader);`

```php
<?php
$smokescreen->loadRelationsVia(new MyRelationsLoader);
```

- You only need to set this if you plan to use a different loader than the default,
- We provide `RelationsLoader` as the default which eager-loads relationships for collection resources.
- Your custom loader should implement the `RelationsLoaderInterface` interface and provide a `load()` method.

### response(): Access the generated response object

`$response = $smokescreen->response(int $statusCode = 200, array $headers = [], int $options = 0);`

```php
<?php
$smokescreen->response()
    ->header('X-Custom-Header', 'boo')
    ->setStatusCode(405);
```

- This method returns an `\Illuminate\Http\JsonResponse` object so it is not chainable.
- All supported `JsonResponse` methods can be applied.
- You can still return `response()` directly from your controller since it is a `JsonResponse` object.
- You can alternatively use `withResponse($callback)` to apply changes, and still support chainability.
- Note: the first call to `response()` caches the result so that the entire data set is not re-generated every time,
this means passing any parameters on subsequent calls will be ignored. You can use `clearResponse()` or manipulate the
`JsonResponse` object directly.

### freshResponse(): Generate a fresh Response object

`$response = $smokescreen->freshResponse(int $statusCode = 200, array $headers = [], int $options = 0);`

- Unlike `response()` this method returns a fresh non-cached JsonResponse object (by calling `clearResponse()` first).
- This method returns an `\Illuminate\Http\JsonResponse` object so it is not chainable. See `withResponse()` for a
chainable method.
- All supported `JsonResponse` methods can be applied.

### withResponse(): Apply changes to the generated response object

`$smokescreen->withResponse(callable $apply);`

```php
<?php
$smokescreen->withResponse(function (JsonResponse $response) {
    $response->header('X-Crypto-Alert', 'all your coins are worthless!');
});

```

- Provide a callback that accepts a `JsonResponse` object and manipulates the response 
- This method is chainable unlike `response()`

### clearResponse(): Clear any cached response

`$smokescreen->clearResponse();`

```php
<?php
$smokescreen->response();       // Data is generated, response object is built and cached
$smokescreen->response(500);    // NOPE - Cached, wont be changed!
$smokescreen->clearResponse();
$smokescreen->response(500);    // Response is re-generated
```

- Reset's any cached response object

## Transformers

### Example Transformer

```php
<?php
class PostTransformer extends AbstractTransformer
{
    protected $includes = [
        'user' => 'default|relation:user|method:includeTheDamnUser',
        'comments' => 'relation',
    ];

    public function transform(Post $post): array
    {
        return [
            'id' => $post->id,
            'user' => $this->when($post->user_id, [
                'id' => $post->user_id,
            ]),
            'title' => $post->title,
            'summary' => $post->summary,
            'body' => $post->body,
            'created_at' => utc_datetime($post->created_at),
            'updated_at' => utc_datetime($post->updated_at),
        ];
    }

    public function includeTheDamnUser(Post $post)
    {
        return $this->item($post->user); // Infer Transformer
    }

    public function includeComments(Post $post)
    {
        return $this->collection($post->comments, new CommentTransformer);
    }
}
```

- You declare your available includes via the `$includes` array
- Each include accepts 0 or more of the following directives:
    - `default`: This include is always enabled regardless of the requested includes
    - `relation`: Indicates that a relation should be eager-loaded.  If the relation name is different 
    specify it as `relation:othername`
    - `method`: By default the include key is mapped to `include{IncludeKey}` you can provide the method 
    to be used instead
- Your `transform()` method should return an array.
- Define your include methods in the format `include{IncludeKey}(Model)` - they should return either a 
`collection()` or an `item()`
- `when()` is a simple helper method which accepts a condition and returns 
either the given value when true, or null (by default) when false.  In the above example
the `"user"` node will be `null` if there is no `user_id` set on the `$post` object.
