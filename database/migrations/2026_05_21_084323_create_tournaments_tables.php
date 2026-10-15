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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('daora_id')->constrained('daoras')->cascadeOnDelete();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default('active'); // active, finished
            $table->timestamps();
        });

        Schema::create('daora_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daora_id')->constrained('daoras')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['daora_id', 'tournament_id']);
        });

        Schema::create('chess_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('white_player_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('black_player_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, playing, finished, aborted
            $table->text('pgn')->nullable();
            $table->integer('white_time_left')->default(300); // 5 minutes in seconds
            $table->integer('black_time_left')->default(300);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chess_matches');
        Schema::dropIfExists('daora_tournament');
        Schema::dropIfExists('tournaments');
    }
};
