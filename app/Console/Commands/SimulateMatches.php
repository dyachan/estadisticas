<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\MatchSimulation;
use Illuminate\Console\Command;

class SimulateMatches extends Command
{
    protected $signature = 'simulate:matches {n : Number of matches to simulate}';
    protected $description = 'Simulate N matches distributing stored teams evenly, then display standings';

    public function handle(): int
    {
        $n = (int) $this->argument('n');

        if ($n < 1) {
            $this->error('N must be at least 1.');
            return self::FAILURE;
        }

        $teams = Team::all();

        if ($teams->count() < 2) {
            $this->error('Need at least 2 teams in the database.');
            return self::FAILURE;
        }

        // Build N pairings with round-robin distribution
        $ids     = $teams->pluck('id')->values()->all();
        $pairs   = $this->buildRoundRobin($ids, $n);

        // Index teams by id for quick access
        $teamMap = $teams->keyBy('id');

        // In-memory counters (avoid N DB reads mid-loop)
        $stats = [];
        foreach ($teams as $team) {
            $stats[$team->id] = ['wins' => 0, 'draws' => 0, 'losses' => 0, 'played' => 0];
        }

        $bar = $this->output->createProgressBar($n);
        $bar->start();

        foreach ($pairs as [$idA, $idB]) {
            $teamA = $teamMap[$idA];
            $teamB = $teamMap[$idB];

            $summary = $this->runMatch($teamA, $teamB);

            $goalsA = $summary['GoalsA'];
            $goalsB = $summary['GoalsB'];

            $stats[$idA]['played']++;
            $stats[$idB]['played']++;

            if ($goalsA > $goalsB) {
                $stats[$idA]['wins']++;
                $stats[$idB]['losses']++;
            } elseif ($goalsB > $goalsA) {
                $stats[$idB]['wins']++;
                $stats[$idA]['losses']++;
            } else {
                $stats[$idA]['draws']++;
                $stats[$idB]['draws']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Persist counters to DB
        foreach ($stats as $id => $s) {
            Team::where('id', $id)->update([
                'wins'           => \DB::raw("wins + {$s['wins']}"),
                'draws'          => \DB::raw("draws + {$s['draws']}"),
                'losses'         => \DB::raw("losses + {$s['losses']}"),
                'matches_played' => \DB::raw("matches_played + {$s['played']}"),
            ]);
        }

        // Reload and display standings
        $standings = Team::all()->sortByDesc('wins')->sortByDesc(fn($t) => $t->wins * 3 + $t->draws);

        $this->table(
            ['Team', 'MP', 'W', 'D', 'L', 'Pts'],
            $standings->map(fn($t) => [
                $t->name,
                $t->matches_played,
                $t->wins,
                $t->draws,
                $t->losses,
                $t->wins * 3 + $t->draws,
            ])->values()->all()
        );

        return self::SUCCESS;
    }

    /**
     * Generate exactly $n pairs from $ids using repeated round-robin rounds.
     * Each round contains every possible pair once, shuffled randomly.
     */
    private function buildRoundRobin(array $ids, int $n): array
    {
        $allPairs = [];
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $allPairs[] = [$ids[$i], $ids[$j]];
            }
        }

        $result = [];
        while (count($result) < $n) {
            $round = $allPairs;
            shuffle($round);
            // Randomly swap home/away for variety
            $round = array_map(fn($p) => rand(0, 1) ? $p : [$p[1], $p[0]], $round);
            foreach ($round as $pair) {
                $result[] = $pair;
                if (count($result) >= $n) {
                    break;
                }
            }
        }

        return $result;
    }

    private function runMatch(Team $teamA, Team $teamB): array
    {
        $match = new MatchSimulation();
        $match->loadTeams($teamA->toSimulationFormat(), $teamB->toSimulationFormat());

        for ($i = 1; $i <= MatchSimulation::TICKS_PER_MATCH; $i++) {
            $match->update();
        }

        return $match->getSummary();
    }
}
