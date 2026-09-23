<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('knowledge_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->json('knowledge_source_ids')->nullable();
            $table->foreignId('research_run_id')->nullable()->constrained();
            $table->json('parent_insight_ids')->nullable();

            $table->enum('status', ['active', 'outdated', 'superseded', 'rejected'])->default('active');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('type', 64);

            $table->text('body');
            $table->text('user_body')->nullable();

            $table->json('payload')->nullable();
            $table->string('model', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'status', 'type']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('knowledge_insights');
    }

};
