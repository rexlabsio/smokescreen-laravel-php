<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['id', 'title', 'body'];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}