<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alter halakas table
        Schema::table('halakas', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
            $table->foreign('teacher_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // 2. Alter students table
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('halaka_id')->nullable();
            $table->foreign('halaka_id')
                ->references('id')->on('halakas')
                ->onDelete('set null');
        });

        // 3. Migrate data
        $halakas = DB::table('halakas')->get();
        foreach ($halakas as $halaka) {
            DB::table('students')
                ->where('teacher_id', $halaka->teacher_id)
                ->update(['halaka_id' => $halaka->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['halaka_id']);
            $table->dropColumn('halaka_id');
        });

        Schema::table('halakas', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            // NOTE: Changing back to non-nullable might fail if there are nulls,
            // but this is the standard revert.
            $table->unsignedBigInteger('teacher_id')->nullable(false)->change();
            $table->foreign('teacher_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }
};
