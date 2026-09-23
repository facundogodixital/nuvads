<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class KnowledgeInsight extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'brand_id',
        'knowledge_source_ids',
        'research_run_id',
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
            'brand_id' => 'integer',
            'knowledge_source_ids' => 'array',
            'research_run_id' => 'integer',
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


    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }


    public function getEffectiveBody(): string
    {
        return $this->user_body ?? $this->body;
    }

}
