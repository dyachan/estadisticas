<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->json('home_snapshot');
            $table->json('opponent_snapshot');
            $table->unsignedInteger('goals_for')->default(0);
            $table->unsignedInteger('goals_against')->default(0);
            $table->string('result'); // 'win', 'draw', 'loss'
            $table->longText('replay')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
