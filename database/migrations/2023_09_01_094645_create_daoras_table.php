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
        Schema::create('daoras', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('photo')->nullable();
    $table->string('photo_hash')->nullable();
    $table->unsignedBigInteger('admin_id')->nullable(); // نخلي العمود فقط بدون foreign key
    $table->integer("number_of_students")->default(0);
    $table->boolean('showable')->default(true);
    $table->timestamps();


        
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daoras');
    }
};
