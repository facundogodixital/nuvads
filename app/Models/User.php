<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'is_owner',
        'client_id',
        'google_id',
        'is_enabled',
        'api_token_hash',
        'api_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'api_token_hash',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'username' => 'string',
            'password' => 'hashed',
            'google_id' => 'string',
            'is_owner' => 'boolean',
            'client_id' => 'integer',
            'is_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'api_token_hash' => 'string',
            'remember_token' => 'string',
            'api_token_expires_at' => 'datetime',
        ];
    }


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }


    public function isAccountAccessEnabled(): bool
    {
        $client = $this->client;
        $userIsEnabled = $this->is_enabled && !$this->trashed();
        $clientIsEnabled = $client !== null && $client->is_enabled && !$client->trashed();

        return $userIsEnabled && $clientIsEnabled;
    }

}
