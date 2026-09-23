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
        'brand_logos',
        'brand_colors',
        'brand_offer_description',
        'brand_differentiators_description',
        'brand_history_description',
        'brand_customers_description',
        'brand_customers_needs_description',
        'brand_fonts',
        'brand_visual_style_description',
        'brand_tone_of_voice_description',
        'brand_customers_valued_aspects_description',
        'brand_customers_faq_description',
        'brand_communication_topics_description',
        'brand_content_opportunities_description',
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
            'brand_logos' => 'array',
            'brand_colors' => 'array',
            'brand_offer_description' => 'string',
            'brand_differentiators_description' => 'string',
            'brand_history_description' => 'string',
            'brand_customers_description' => 'string',
            'brand_customers_needs_description' => 'string',
            'brand_fonts' => 'array',
            'brand_visual_style_description' => 'string',
            'brand_tone_of_voice_description' => 'string',
            'brand_customers_valued_aspects_description' => 'string',
            'brand_customers_faq_description' => 'string',
            'brand_communication_topics_description' => 'string',
            'brand_content_opportunities_description' => 'string',
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
