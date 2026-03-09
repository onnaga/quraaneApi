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
        Schema::create('achieved_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();

            // Optional: link to the specific desired goals record
            // $table->unsignedBigInteger('desired_goal_id')->nullable(); 
            // $table->foreign('desired_goal_id')->references('id')->on('desired_goals')->onDelete('set null');

            $table->text('quranic_goals')->nullable()->comment('الأهداف القرآنية المنجزة');
            $table->text('educational_goals')->nullable()->comment('الأهداف التربوية المنجزة');
            $table->text('scientific_goals')->nullable()->comment('الأهداف العلمية المنجزة');
            $table->text('social_goals')->nullable()->comment('الأهداف الاجتماعية المنجزة');
            $table->text('preparation_for_advocacy')->nullable()->comment('التهيئة لدور في العمل الدعوي المنجزة');

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achieved_goals');
    }
};
