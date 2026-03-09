<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Independent player entity
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->float('max_speed')->default(0.5);
            $table->float('accuracy')->default(0.5);
            $table->float('control')->default(0.5);
            $table->float('reaction')->default(0.5);
            $table->float('dribble')->default(0.5);
            $table->float('strength')->default(0.5);
            $table->float('endurance')->default(0.5);
            $table->float('scan_with_ball')->default(0.5);
            $table->float('scan_without_ball')->default(0.5);
            $table->timestamps();
        });

        // Full attribute snapshot stored on every change
        Schema::create('player_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_player_id')->constrained()->cascadeOnDelete();
            $table->float('max_speed');
            $table->float('accuracy');
            $table->float('control');
            $table->float('reaction');
            $table->float('dribble');
            $table->float('strength');
            $table->float('endurance');
            $table->float('scan_with_ball');
            $table->float('scan_without_ball');
            $table->timestamp('recorded_at')->useCurrent();
        });

        // User-owned tactic: a reusable rule set for one player position
        Schema::create('tactics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->float('default_zone_x')->nullable(); // 0–100 field percentage
            $table->float('default_zone_y')->nullable();
            $table->json('rules_with_ball')->nullable();
            $table->json('rules_without_ball')->nullable();
            $table->timestamps();
        });

        // Story mode team
        Schema::create('game_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->timestamps();
        });

        // Temporal pivot: a player can be in at most one active team at a time
        Schema::create('game_team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position_index');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
        });

        // Strategy version: each change creates a new row; latest = active
        Schema::create('strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_team_id')->constrained()->cascadeOnDelete();
            $table->json('formation')->nullable(); // denormalized blob: zones + rules per player
            $table->timestamps();
        });

        // Which tactic each player uses in a given strategy version
        Schema::create('strategy_player_tactics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tactic_id')->constrained()->cascadeOnDelete();

            $table->unique(['strategy_id', 'game_player_id']);
        });

        // Story mode matches
        Schema::create('story_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_team_id')->constrained('game_teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('game_teams')->nullOnDelete();
            $table->foreignId('home_strategy_id')->nullable()->constrained('strategies')->nullOnDelete();
            $table->foreignId('away_strategy_id')->nullable()->constrained('strategies')->nullOnDelete();
            $table->timestamp('played_at')->useCurrent();
            $table->unsignedInteger('home_score')->default(0);
            $table->unsignedInteger('away_score')->default(0);
            $table->longText('replay')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_matches');
        Schema::dropIfExists('strategy_player_tactics');
        Schema::dropIfExists('strategies');
        Schema::dropIfExists('game_team_players');
        Schema::dropIfExists('game_teams');
        Schema::dropIfExists('tactics');
        Schema::dropIfExists('player_snapshots');
        Schema::dropIfExists('game_players');
    }
};
