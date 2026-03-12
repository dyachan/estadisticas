<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_team_ui', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_team_id')->constrained()->cascadeOnDelete();
            $table->string('color', 7)->default('#fd9946');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_team_ui');
    }
};
