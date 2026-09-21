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
            $table->foreignId('knowledge_source_id')->nullable()->constrained()->nullOnDelete();

            $table->json('parent_insight_ids')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('type', 64);
            $table->text('body');
            $table->text('user_body')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('payload')->nullable();

            $table->char('run_id', 36);
            $table->string('model', 64)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->enum('status', ['active', 'superseded', 'rejected'])->default('active');
            $table->boolean('is_user_edited')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'status', 'type']);
            $table->index(['knowledge_source_id', 'status']);
            $table->index('run_id');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('knowledge_insights');
    }

};
