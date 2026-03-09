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
        Schema::create('desired_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();

            $table->text('quranic_goals')->nullable()->comment('الأهداف القرآنية المرجوة');
            $table->text('educational_goals')->nullable()->comment('الأهداف التربوية المرجوة');
            $table->text('scientific_goals')->nullable()->comment('الأهداف العلمية المرجوة');
            $table->text('social_goals')->nullable()->comment('الأهداف الاجتماعية المرجوة');
            $table->text('preparation_for_advocacy')->nullable()->comment('التهيئة لدور في العمل الدعوي');

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
        Schema::dropIfExists('desired_goals');
    }
};
