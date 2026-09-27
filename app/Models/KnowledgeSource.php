<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class KnowledgeSource extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'brand_id',
        'type',
        'title',
        'payload',
        'payload_s3_path',
        'file_s3_path',
        'source_ref',
        'captured_at',
        'status',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'brand_id' => 'integer',
            'type' => 'string',
            'title' => 'string',
            'payload' => 'array',
            'payload_s3_path' => 'string',
            'file_s3_path' => 'string',
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


    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

}
