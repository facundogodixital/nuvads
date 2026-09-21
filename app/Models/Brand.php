<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Brand extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'client_id',
        'website_url',
        'google_maps_url',
        'instagram_username',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'google_maps_url' => 'string',
            'instagram_username' => 'string',
            'website_url' => 'string',
            'client_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

}
