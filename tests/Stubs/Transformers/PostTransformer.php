<?php
namespace Rexlabs\Laravel\Smokescreen\Tests\Stubs\Transformers;

use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Smokescreen\Transformer\AbstractTransformer;

class PostTransformer extends AbstractTransformer
{
    protected $includes = [
        'user',
        'comments',
    ];

    public function transform(Post $post)
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            // No body
        ];
    }

    public function includeUser(Post $post)
    {
        return $this->item(['email' => 'alice@example.com']);
    }

    public function includeComments(Post $post)
    {
        return $this->collection([
            [
                'comments' => 'Great post',
            ],
            [
                'comments' => 'I agree',
            ]
        ]);
    }
}