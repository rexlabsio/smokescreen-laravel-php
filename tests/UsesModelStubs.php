<?php

namespace Rexlabs\Laravel\Smokescreen\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\User;

/**
 * This trait can be included within test classes.
 * It adds methods which to be called at the start of a test to setup some
 * default models.
 */
trait UsesModelStubs
{
    protected function createSchemas()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')
                ->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->uuid('origin')->nullable();
            $table->timestamps();
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->text('comments');
            $table->timestamps();
        });
    }

    protected function createModels()
    {
        /** @var User $user */
        $user = User::create([
                'name'     => 'Some User',
                'email'    => 'some.user@example.com',
                'password' => Hash::make('somepassword'),
            ]);
        /** @var Post $post */
        $post = Post::create([
                'user_id' => $user->id,
                'title'   => 'Example post',
                'body'    => 'Post body',
            ]);
        $post->comments()
            ->create([
                    'user_id'  => $user->id,
                    'title'    => 'First comment',
                    'comments' => 'FP',
                ]);
        $post->comments()
            ->create([
                    'user_id'  => $user->id,
                    'title'    => 'Another comment',
                    'comments' => 'That is all',
                ]);
    }
}
