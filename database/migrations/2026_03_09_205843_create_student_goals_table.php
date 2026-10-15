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
        Schema::create('student_goals', function (Blueprint $table) {
            $table->id();

            // الطالب الذي تم تعيين الهدف له
            $table->unsignedBigInteger('user_id');
            // المعلم الذي أضاف الهدف
            $table->unsignedBigInteger('teacher_id')->nullable();

            // نوع الهدف (قرآني، تربوي،
            $table->enum('category', [
                'quranic',
                'educational',
                'scientific',
                'social',
                'advocacy',
            ])->comment('قسم الهدف');

            // نص الهدف المرجو
            $table->text('goal_text')->comment('نص الهدف المطلوب إنجازه');

            // حالة الهدف (تم / لم يتم / قيد الإنجاز / معلق)
            $table->enum('status', ['pending', 'in_progress', 'achieved', 'not_achieved'])
                ->default('pending')
                ->comment('حالة إنجاز الهدف');

            // تعليقات أو ملاحظات المعلم على التقييم الأخير
            $table->text('comments')->nullable()->comment('تعليقات المعلم');

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
        Schema::dropIfExists('student_goals');
    }
};
