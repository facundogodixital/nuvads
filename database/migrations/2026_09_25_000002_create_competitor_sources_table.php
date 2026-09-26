<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('competitor_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('competitor_id')->constrained();

            $table->enum('type', ['web_page', 'instagram_post', 'meta_ad', 'google_review']);
            $table->string('title', 255);
            $table->string('s3_path', 512)->nullable();
            $table->json('payload')->nullable();
            $table->string('source_ref', 512)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->enum('status', ['pending', 'ready', 'failed'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['competitor_id', 'type']);
            $table->index(['competitor_id', 'status']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('competitor_sources');
    }

};
