<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class CompetitorSource extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'competitor_id',
        'type',
        'title',
        's3_path',
        'payload',
        'source_ref',
        'captured_at',
        'status',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'competitor_id' => 'integer',
            'type' => 'string',
            'title' => 'string',
            's3_path' => 'string',
            'payload' => 'array',
            'source_ref' => 'string',
            'captured_at' => 'datetime',
            'status' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }


    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

}
