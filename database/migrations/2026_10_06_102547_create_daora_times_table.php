<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daora_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daora_id');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');
            
            // أيام الأسبوع (أرقام من 1 إلى 7 أو نصية)
            // سنخزنها كنص JSON مثل: [1, 2, 3, 4, 5] يمثل الأيام (1=الأحد، 2=الاثنين...)
            $table->json('days_of_week'); 
            
            // يمكن للمدير إنشاء حتى 3 أوقات (فترات) للدورة في اليوم
            $table->time('start_time_1')->nullable();
            $table->time('end_time_1')->nullable();
            
            $table->time('start_time_2')->nullable();
            $table->time('end_time_2')->nullable();
            
            $table->time('start_time_3')->nullable();
            $table->time('end_time_3')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daora_times');
    }
};
