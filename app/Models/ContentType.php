<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ContentType extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'key',
        'name',
        'description',
        'instructions',
        'inputs',
        'angles',
        'layouts',
    ];

    // Al frontend viaja solo lo que muestra la tarjeta: id, key, name y description, más si la marca lo puede usar,
    // que suma ContentTypeService.
    protected $hidden = [
        'instructions',
        'inputs',
        'angles',
        'layouts',
        'created_at',
        'updated_at',
        'deleted_at',
        'deleted_at_ts',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'key' => 'string',
            'name' => 'string',
            'description' => 'string',
            'instructions' => 'string',
            'inputs' => 'array',
            'angles' => 'array',
            'layouts' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'deleted_at_ts' => 'integer',
        ];
    }

}
