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
        $blocked = $this->blocked($state);

        if ($this->room($state, $blocked, $state->you->head->adjacent($preferred)) >= $length) {
            return $preferred;
        }

        $best = null;
        $bestRank = null;

        foreach (($this->classifier)($state) as $move) {
            if ($move->is(Classification::END)) {
                continue;
            }

            $room = $this->room($state, $blocked, $state->you->head->adjacent($move->direction));
            $rank = [$room >= $length ? 0 : 1, $move->is(Classification::SAFE) ? 0 : 1, -$room];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $move->direction;
                $bestRank = $rank;
            }
        }

        return $best ?? $preferred;
    }

    /**
     * Squares no head can enter next turn: every body segment except a tail that
     * moves away (a tail stacked on the segment before it, after eating, stays).
     *
     * @return array<string, true>
     */
    private function blocked(GameState $state): array
    {
        $blocked = [];
        foreach ($state->board->snakes as $snake) {
            $segments = \iterator_to_array($snake->body, false);
            $count = \count($segments);
            foreach ($segments as $i => $segment) {
                $isTail = $i === $count - 1 && $count > 1 && ! $segment->equals($segments[$i - 1]);
                if (! $isTail) {
                    $blocked[self::key($segment)] = true;
                }
            }
        }
        return $blocked;
    }

    /**
     * Number of squares reachable from $start, $start included.
     *
     * @param array<string, true> $blocked
     */
    private function room(GameState $state, array $blocked, Coordinate $start): int
    {
        if (! $state->board->contains($start) || isset($blocked[self::key($start)])) {
            return 0;
        }

        $seen = [self::key($start) => true];
        $queue = [$start];

        while ($queue !== []) {
            $square = \array_shift($queue);
            foreach (Direction::cases() as $direction) {
                $next = $square->adjacent($direction);
                $key = self::key($next);
                if (isset($seen[$key]) || isset($blocked[$key]) || ! $state->board->contains($next)) {
                    continue;
                }
                $seen[$key] = true;
                $queue[] = $next;
            }
        }

        return \count($seen);
    }

    private static function key(Coordinate $square): string
    {
        return $square->x . ',' . $square->y;
    }
}
