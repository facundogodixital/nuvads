<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class CompetitorInsight extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'competitor_id',
        'competitor_source_ids',
        'competitor_research_run_id',
        'parent_insight_ids',
        'status',
        'level',
        'type',
        'body',
        'user_body',
        'payload',
        'model',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'competitor_id' => 'integer',
            'competitor_source_ids' => 'array',
            'competitor_research_run_id' => 'integer',
            'parent_insight_ids' => 'array',
            'status' => 'string',
            'level' => 'integer',
            'type' => 'string',
            'body' => 'string',
            'user_body' => 'string',
            'payload' => 'array',
            'model' => 'string',
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
