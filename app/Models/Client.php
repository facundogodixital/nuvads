<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Client extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'timezone',
        'is_enabled',
        'country_code',
        'login_identifier',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'timezone' => 'string',
            'is_enabled' => 'boolean',
            'country_code' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'login_identifier' => 'string',
        ];
    }


    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }


    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

}
