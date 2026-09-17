<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class() extends Migration
{


    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->string('google_id')->nullable()->unique();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();

            $table->boolean('is_owner')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'username']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('users');
    }

};
