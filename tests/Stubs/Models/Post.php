<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    protected $guarded = [];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
