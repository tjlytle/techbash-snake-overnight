<?php

declare(strict_types=1);

namespace BattleSnake\Snake;

use BattleSnake\Analyzer\Move\Classification;
use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Analyzer\OpenArea;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Strategy\Strategy;

/**
 * Vetoes a move into a pocket smaller than our length. Asks the preferred strategy
 * first and keeps its answer when the square it leads to opens onto at least our
 * length of reachable squares (flood fill: bodies block, tails free up next turn).
 *
 * Otherwise picks among the non-END moves: enough room first, then SAFE, then the
 * larger area. With no move left but END it returns the preferred answer, for
 * Fallback to catch.
 */
final class AvoidDeadEnds implements Strategy
{
    public function __construct(
        private readonly Strategy $preferred,
        private readonly MoveClassifier $classifier,
    ) {
    }

    public function __invoke(Game $game, GameState $state): Direction
    {
        $preferred = ($this->preferred)($game, $state);
        $length = $state->you->length;
        $area = new OpenArea($state);

        if ($area->from($state->you->head->adjacent($preferred)) >= $length) {
            return $preferred;
        }

        $best = null;
        $bestRank = null;

        foreach (($this->classifier)($state) as $move) {
            if ($move->is(Classification::END)) {
                continue;
            }

            $room = $area->from($state->you->head->adjacent($move->direction));
            $rank = [$room >= $length ? 0 : 1, $move->is(Classification::SAFE) ? 0 : 1, -$room];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $move->direction;
                $bestRank = $rank;
            }
        }

        return $best ?? $preferred;
    }
}
