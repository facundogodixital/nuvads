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
        'knowledge_source_id',
        'parent_insight_ids',
        'level',
        'type',
        'body',
        'user_body',
        'confidence',
        'payload',
        'run_id',
        'model',
        'prompt_version',
        'status',
        'is_user_edited',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'brand_id' => 'integer',
            'knowledge_source_id' => 'integer',
            'parent_insight_ids' => 'array',
            'level' => 'integer',
            'type' => 'string',
            'body' => 'string',
            'user_body' => 'string',
            'confidence' => 'decimal:2',
            'payload' => 'array',
            'run_id' => 'string',
            'model' => 'string',
            'prompt_version' => 'string',
            'status' => 'string',
            'is_user_edited' => 'boolean',
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


    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }


    public function getEffectiveBody(): string
    {
        return $this->user_body ?? $this->body;
    }

}
