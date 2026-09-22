<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ResearchRun extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id', 'brand_id', 'run_id', 'type', 'status', 'input',
        'external_run_id', 'external_dataset_id', 'knowledge_source_ids',
        'started_at', 'finished_at', 'last_checked_at', 'error_message',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'brand_id' => 'integer',
            'run_id' => 'string',
            'type' => 'string',
            'status' => 'string',
            'input' => 'array',
            'external_run_id' => 'string',
            'external_dataset_id' => 'string',
            'knowledge_source_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'error_message' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }


    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }


    public function knowledgeInsights(): HasMany
    {
        return $this->hasMany(KnowledgeInsight::class, 'run_id', 'run_id');
    }

}
