<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_reports', function (Blueprint $table) {
            $table->id();

            // نوع التقرير: halaka (حلقة) أو mosque (مسجد)
            $table->enum('report_type', ['halaka', 'mosque']);

            // من أنشأ التقرير (أستاذ أو مشرف)
            $table->unsignedBigInteger('author_id');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');

            // الدورة المرتبطة
            $table->unsignedBigInteger('daora_id');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');

            // الحلقة (فقط لتقارير الحلقات)
            $table->unsignedBigInteger('halaka_id')->nullable();
            $table->foreign('halaka_id')->references('id')->on('halakas')->onDelete('set null');

            // ===== بيانات الإحصاءات المحسوبة (JSON) =====
            $table->json('statistics_snapshot')->nullable();

            // ===== ملاحظات وتعليقات على الإحصاءات =====
            $table->json('field_notes')->nullable();

            // ===== بيانات الطلاب التفصيلية =====
            $table->json('students_without_homework')->nullable();
            $table->json('absent_students_justifications')->nullable();

            // ===== خانات المشرف الإضافية (لتقارير المسجد فقط) =====
            $table->text('media_file_notes')->nullable();
            $table->text('admin_file_notes')->nullable();
            $table->text('events_file_notes')->nullable();
            $table->text('logistics_file_notes')->nullable();

            $table->json('teachers_notes')->nullable();

            // ===== ملاحظات عامة =====
            $table->text('general_notes')->nullable();
            $table->text('notes_for_management')->nullable();

            // ===== حالة التقرير =====
            $table->enum('status', ['draft', 'shared'])->default('draft');

            // ===== من طلب التقرير (إن وُجد) =====
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_reports');
    }
};
