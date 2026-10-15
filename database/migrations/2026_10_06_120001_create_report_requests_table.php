<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_requests', function (Blueprint $table) {
            $table->id();

            // من طلب التقرير
            $table->unsignedBigInteger('requester_id');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');

            // من المطلوب منه التقرير
            $table->unsignedBigInteger('target_id');
            $table->foreign('target_id')->references('id')->on('users')->onDelete('cascade');

            // الدورة المرتبطة
            $table->unsignedBigInteger('daora_id');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');

            // نوع الطلب
            $table->enum('request_type', ['halaka_report', 'mosque_report']);

            // حالة الطلب
            $table->enum('status', ['pending', 'submitted'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_requests');
    }
};
