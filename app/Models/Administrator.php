<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;


class Administrator extends Authenticatable
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'is_enabled',
    ];

    protected $hidden = [
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'google_id' => 'string',
            'is_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'remember_token' => 'string',
        ];
    }

}
