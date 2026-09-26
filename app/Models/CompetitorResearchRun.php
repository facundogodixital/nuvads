<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class CompetitorResearchRun extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id', 'competitor_id', 'type', 'status', 'input',
        'external_run_id', 'external_dataset_id', 'competitor_source_ids',
        'started_at', 'finished_at', 'last_checked_at', 'status_message',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'competitor_id' => 'integer',
            'type' => 'string',
            'status' => 'string',
            'input' => 'array',
            'external_run_id' => 'string',
            'external_dataset_id' => 'string',
            'competitor_source_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'status_message' => 'string',
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
