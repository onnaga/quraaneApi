<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. تحديث جدول المستخدمين
        Schema::table('users', function (Blueprint $table) {
            $table->date('temp_birth_date')->nullable()->after('age');
            $table->integer('family_members_count')->nullable()->after('family_status');
            $table->text('notes')->nullable();
            $table->text('skills')->nullable();      // المهارات
            $table->text('certificates')->nullable(); // الشهادات
        });

        // تحويل العمر إلى تاريخ ميلاد
        DB::statement("UPDATE users SET temp_birth_date = STR_TO_DATE(CONCAT(YEAR(CURDATE()) - age, '-01-01'), '%Y-%m-%d') WHERE age IS NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('temp_birth_date', 'age');
        });

        // 2. تحديث جدول الطلاب
        Schema::table('students', function (Blueprint $table) {
            $table->string('current_grade')->nullable()->after('halaka_id');
            $table->string('school_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('landline_phone')->nullable();
            $table->string('other_phone')->nullable();
            $table->string('preferred_contact')->nullable()->comment('Whatsapp preferred contact');
            $table->text('previous_mosques')->nullable();
            // تم حذف previous_courses من هنا بناءً على طلبك
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'current_grade', 'school_name', 'father_phone', 'mother_phone',
                'landline_phone', 'other_phone', 'preferred_contact', 'previous_mosques',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['family_members_count', 'notes', 'skills', 'certificates']);
            $table->integer('temp_age')->nullable();
        });

        DB::statement('UPDATE users SET temp_age = YEAR(CURDATE()) - YEAR(age) WHERE age IS NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age');
            $table->renameColumn('temp_age', 'age');
        });
    }
};
