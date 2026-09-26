<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->string('name');
            $table->string('google_maps_url', 2048)->nullable();
            $table->string('instagram_username', 255)->nullable();
            $table->string('meta_ads_url', 2048)->nullable();
            $table->string('website_url', 2048)->nullable();

            $table->text('competitor_offer_description')->nullable();
            $table->text('competitor_differentiators_description')->nullable();
            $table->text('competitor_customers_description')->nullable();
            $table->text('competitor_communication_description')->nullable();
            $table->text('competitor_strengths_description')->nullable();
            $table->text('competitor_weaknesses_description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }

};
