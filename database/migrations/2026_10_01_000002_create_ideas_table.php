<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('content_type_id')->constrained();

            $table->string('title');
            $table->string('angle')->nullable();
            $table->json('knowledge_insight_ids');
            $table->json('knowledge_source_ids');

            // String abierto, sin lista cerrada en la base: hoy su único valor es chosen.
            $table->string('status');
            $table->string('model');

            $table->timestamps();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }

};
