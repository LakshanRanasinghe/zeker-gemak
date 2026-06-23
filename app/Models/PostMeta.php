<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMeta extends Model
{
    protected $table = 'post_meta';

    protected $fillable = [
        'post_id',
        'meta_key',
        'meta_value',
    ];

    public function setMetaValueAttribute($value)
    {
        $this->attributes['meta_value'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getMetaValueAttribute($value)
    {
        return is_string($value) && is_array(json_decode($value, true)) ? json_decode($value, true) : $value;
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
