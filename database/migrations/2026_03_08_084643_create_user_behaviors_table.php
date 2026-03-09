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
        Schema::create('user_behaviors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();

            $table->text('traits')->nullable()->comment('الطباع');
            $table->text('habits')->nullable()->comment('العادات');
            $table->text('discipline')->nullable()->comment('الانضباط');
            $table->text('morals')->nullable()->comment('الأخلاق');
            $table->text('positive_actions')->nullable()->comment('أفعال ايجابية لافتة');
            $table->text('negative_actions')->nullable()->comment('أفعال سلبية لافتة');

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
        Schema::dropIfExists('user_behaviors');
    }
};
