<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('latests', function (Blueprint $table) {
            $table->id();
            $table->json('quran')->nullable();
            $table->json('hadith')->nullable();
            $table->json('activitis')->nullable();
            $table->json('note')->nullable();
            $table->json('q_homework')->nullable();
            $table->json('h_homework')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('latests');
    }
};
