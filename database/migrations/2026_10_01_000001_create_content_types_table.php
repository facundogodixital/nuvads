<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('content_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->string('description');

            $table->text('instructions');
            $table->json('inputs');
            $table->json('angles')->nullable();
            $table->json('layouts')->nullable();

            $table->timestamps();
            $table->softDeletes();
            // 0 mientras el tipo está activo; al borrarlo se completa junto con deleted_at, con el mismo momento.
            $table->unsignedBigInteger('deleted_at_ts')->default(0);

            // Un solo tipo activo por key. Con deleted_at no alcanza: en MySQL los null no chocan entre sí.
            $table->unique(['key', 'deleted_at_ts']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }

};
