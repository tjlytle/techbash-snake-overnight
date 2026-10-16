<?php

declare(strict_types=1);

namespace BattleSnake\Snake;

use BattleSnake\Analyzer\Move\Classification;
use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Analyzer\OpenArea;
use BattleSnake\Domain\Coordinate;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Strategy\Strategy;

/**
 * Heads for food at any distance. Food counts when no longer snake has its head as near
 * to it as ours. Knows nothing about the phase of the game: the composition decides when
 * it is the opening. With food that counts, ranks the SAFE moves that close in on it and
 * leave at least our length of open area:
 *
 *   1. the nearer to that food
 *   2. the larger open area
 *
 * Ties go to the preferred answer. With no such food, or no such move, the preferred
 * answer stands.
 */
final class OpeningHunt implements Strategy
{
    public function __construct(
        private readonly Strategy $preferred,
        private readonly MoveClassifier $classifier,
    ) {
    }

    public function __invoke(Game $game, GameState $state): Direction
    {
        $preferred = ($this->preferred)($game, $state);
        $food = $this->ourFood($state);

        if ($food === []) {
            return $preferred;
        }

        $here = self::nearest($food, $state->you->head);
        $area = new OpenArea($state);
        $best = null;
        $bestRank = null;

        foreach (($this->classifier)($state) as $move) {
            if (! $move->is(Classification::SAFE)) {
                continue;
            }

            $square = $state->you->head->adjacent($move->direction);
            $distance = self::nearest($food, $square);
            if ($distance >= $here) {
                continue;
            }

            $room = $area->from($square);
            if ($room < $state->you->length) {
                continue;
            }

            $rank = [$distance, -$room, $move->direction === $preferred ? 0 : 1];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $move->direction;
                $bestRank = $rank;
            }
        }

        return $best ?? $preferred;
    }

    /**
     * Food that no longer snake has its head as near to as ours.
     *
     * @return list<Coordinate>
     */
    private function ourFood(GameState $state): array
    {
        $you = $state->you;
        $ours = [];
        foreach ($state->board->food as $food) {
            $distance = self::distance($you->head, $food);
            foreach ($state->board->snakes as $snake) {
                if ($snake->id === $you->id || $snake->head === null || $snake->length <= $you->length) {
                    continue;
                }
                if (self::distance($snake->head, $food) <= $distance) {
                    continue 2;
                }
            }
            $ours[] = $food;
        }
        return $ours;
    }

    /** @param list<Coordinate> $food */
    private static function nearest(array $food, Coordinate $from): int
    {
        return \min(\array_map(static fn(Coordinate $f): int => self::distance($f, $from), $food));
    }

    private static function distance(Coordinate $a, Coordinate $b): int
    {
        return \abs($a->x - $b->x) + \abs($a->y - $b->y);
    }
}
