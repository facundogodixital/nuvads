<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Idea extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'brand_id',
        'content_type_id',
        'title',
        'angle',
        'knowledge_insight_ids',
        'knowledge_source_ids',
        'status',
        'model',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'brand_id' => 'integer',
            'content_type_id' => 'integer',
            'title' => 'string',
            'angle' => 'string',
            'knowledge_insight_ids' => 'array',
            'knowledge_source_ids' => 'array',
            'status' => 'string',
            'model' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

}
