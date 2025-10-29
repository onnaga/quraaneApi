<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daoras', function (Blueprint $table) {

            
            // 2. علاقة اختيارية - لا تحذف الدورة عند حذف المشرف
            $table->foreign('admin_id')
                  ->references('id')->on('users')
                  ->onDelete('set null'); // أو 'restrict' حسب احتياجك
        });

        Schema::table('users', function (Blueprint $table) {

            
            // 2. عند حذف الدورة، احذف جميع المستخدمين المرتبطين بها
            $table->foreign('daora_id')
                  ->references('id')->on('daoras')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {

    }
};