<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->string('name');
            $table->string('google_maps_url', 2048)->nullable();
            $table->string('instagram_username', 255)->nullable();
            $table->string('website_url', 2048)->nullable();

            $table->json('brand_logos')->nullable();
            $table->json('brand_colors')->nullable();

            $table->text('brand_offer_description')->nullable();
            $table->text('brand_differentiators_description')->nullable();
            $table->text('brand_history_description')->nullable();

            $table->text('brand_customers_description')->nullable();
            $table->text('brand_customers_needs_description')->nullable();

            $table->json('brand_fonts')->nullable();
            $table->text('brand_visual_style_description')->nullable();
            $table->text('brand_tone_of_voice_description')->nullable();

            $table->text('brand_customers_valued_aspects_description')->nullable();
            $table->text('brand_customers_faq_description')->nullable();

            $table->text('brand_communication_topics_description')->nullable();
            $table->text('brand_content_opportunities_description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('brands');
    }

};
