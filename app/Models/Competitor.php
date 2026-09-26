<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Competitor extends Model
{

    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'brand_id',
        'name',
        'google_maps_url',
        'instagram_username',
        'meta_ads_url',
        'website_url',
        'competitor_offer_description',
        'competitor_differentiators_description',
        'competitor_customers_description',
        'competitor_communication_description',
        'competitor_strengths_description',
        'competitor_weaknesses_description',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'brand_id' => 'integer',
            'name' => 'string',
            'google_maps_url' => 'string',
            'instagram_username' => 'string',
            'meta_ads_url' => 'string',
            'website_url' => 'string',
            'competitor_offer_description' => 'string',
            'competitor_differentiators_description' => 'string',
            'competitor_customers_description' => 'string',
            'competitor_communication_description' => 'string',
            'competitor_strengths_description' => 'string',
            'competitor_weaknesses_description' => 'string',
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
