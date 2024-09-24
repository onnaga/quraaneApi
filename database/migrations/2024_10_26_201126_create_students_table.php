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
        Schema::create('students', function (Blueprint $table) {

            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->bigInteger('teacher_id')->unsigned()->nullable();
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('set null');
            $table->bigInteger('point_id')->unsigned()->nullable();
            $table->foreign('point_id')->references('id')->on('points')->onDelete('set null');
            $table->bigInteger('latest_id')->unsigned()->nullable();
            $table->foreign('latest_id')->references('id')->on('latests')->onDelete('set null');
            $table->json('ended_quraan_in_aukaf')->nullable();
            $table->integer('missing_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
