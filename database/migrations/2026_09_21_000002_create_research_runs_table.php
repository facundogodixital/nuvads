<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('research_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->string('type', 64);
            $table->enum('status', ['pending', 'scraping', 'analyzing', 'completed', 'empty', 'failed']);
            $table->json('input');
            $table->string('external_run_id')->nullable();
            $table->string('external_dataset_id')->nullable();
            $table->json('knowledge_source_ids')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'type', 'status']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('research_runs');
    }

};
