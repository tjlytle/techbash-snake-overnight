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
 * Takes food while it is cheap, at any health. Food is cheap when it is within two squares
 * of our head and no snake as long as us or longer has its head as near to it as ours.
 * With cheap food on the board, ranks the SAFE moves that close in on it:
 *
 *   1. the nearer to cheap food
 *   2. the larger open area
 *   3. not onto an edge square
 *
 * Ties go to the preferred answer. With no cheap food, or no SAFE move toward it, the
 * preferred answer stands.
 */
final class GrowWhenSafe implements Strategy
{
    private const int RANGE = 2;

    public function __construct(
        private readonly Strategy $preferred,
        private readonly MoveClassifier $classifier,
    ) {
    }

    public function __invoke(Game $game, GameState $state): Direction
    {
        $preferred = ($this->preferred)($game, $state);
        $food = $this->cheapFood($state);

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

            $rank = [
                $distance,
                -$area->from($square),
                $this->onEdge($state, $square) ? 1 : 0,
                $move->direction === $preferred ? 0 : 1,
            ];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $move->direction;
                $bestRank = $rank;
            }
        }

        return $best ?? $preferred;
    }

    /**
     * Food within RANGE of our head that no snake as long as us or longer is as near to.
     *
     * @return list<Coordinate>
     */
    private function cheapFood(GameState $state): array
    {
        $you = $state->you;
        $cheap = [];
        foreach ($state->board->food as $food) {
            $ours = self::distance($you->head, $food);
            if ($ours > self::RANGE) {
                continue;
            }
            foreach ($state->board->snakes as $snake) {
                if ($snake->id === $you->id || $snake->head === null || $snake->length < $you->length) {
                    continue;
                }
                if (self::distance($snake->head, $food) <= $ours) {
                    continue 2;
                }
            }
            $cheap[] = $food;
        }
        return $cheap;
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

    private function onEdge(GameState $state, Coordinate $square): bool
    {
        return $square->x === 0 || $square->y === 0
            || $square->x === $state->board->width - 1 || $square->y === $state->board->height - 1;
    }
}
