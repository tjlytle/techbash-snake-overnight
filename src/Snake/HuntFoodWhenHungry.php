<?php

declare(strict_types=1);

namespace BattleSnake\Snake;

use BattleSnake\Analyzer\Move\Classification;
use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Coordinate;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Strategy\Strategy;

/**
 * Steps toward the nearest food (Manhattan distance). Knows nothing about health:
 * the composition decides when the snake is hungry enough to run it.
 *
 *   - never picks an END move
 *   - hungry: SAFE moves first, then the one that gets closest to food
 *   - desperate: closest to food first, SAFE only breaks ties
 *
 * With no move left but END it answers UP, for Fallback to catch.
 */
final class HuntFoodWhenHungry implements Strategy
{
    public function __construct(
        private readonly MoveClassifier $classifier,
        private readonly bool $desperate = false,
    ) {
    }

    public function __invoke(Game $game, GameState $state): Direction
    {
        $best = null;
        $bestRank = null;

        foreach (($this->classifier)($state) as $move) {
            if ($move->is(Classification::END)) {
                continue;
            }

            $distance = $this->distanceToFood($state, $state->you->head->adjacent($move->direction));
            $unsafe = $move->is(Classification::SAFE) ? 0 : 1;
            $rank = $this->desperate ? [$distance, $unsafe] : [$unsafe, $distance];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $move->direction;
                $bestRank = $rank;
            }
        }

        return $best ?? Direction::UP;
    }

    private function distanceToFood(GameState $state, Coordinate $from): int
    {
        $nearest = \PHP_INT_MAX;
        foreach ($state->board->food as $food) {
            $nearest = \min($nearest, \abs($food->x - $from->x) + \abs($food->y - $from->y));
        }
        return $nearest;
    }
}
