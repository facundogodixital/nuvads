<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('administrators', function (Blueprint $table): void {
            $table->id();
            $table->string('google_id')->unique();

            $table->string('name');
            $table->string('email');

            $table->boolean('is_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('administrators');
    }

};
