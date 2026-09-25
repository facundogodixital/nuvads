<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('brand_id')->constrained();

            $table->enum('type', [
                'audio',
                'google_review',
                'whatsapp_conversation',
                'instagram_post',
                'web_page',
                'meta_ad',
                'image',
                'adjustment',
            ]);
            $table->string('title', 255);
            $table->string('s3_path', 512)->nullable();
            $table->json('payload')->nullable();
            $table->string('source_ref', 512)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->enum('status', ['pending', 'ready', 'failed'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'type']);
            $table->index(['brand_id', 'status']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }

};
