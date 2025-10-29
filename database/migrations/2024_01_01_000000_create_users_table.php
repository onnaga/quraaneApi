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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone_number')->nullable();
            $table->string('password');
            $table->integer('privilege')->nullable();
            $table->integer('age')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_hash')->nullable();
            $table->unsignedBigInteger('daora_id')->nullable();
            $table->string('family_status')->nullable();
            $table->text('fcm_token')->nullable();

            // ✅ --- التعديلات الجديدة ---
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();

            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('set null');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');
            // --- نهاية التعديلات ---

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('users');
        // Schema::dropIfExists('password_reset_tokens');
        // Schema::dropIfExists('sessions');
    }
};
