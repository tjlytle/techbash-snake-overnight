<?php

declare(strict_types=1);

namespace BattleSnake\Analyzer;

use BattleSnake\Domain\Coordinate;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;

/**
 * Flood fill over one board: how many squares a head could reach from a square.
 * Bodies block; a tail that moves away next turn does not (a tail stacked on the
 * segment before it, after eating, stays).
 */
final class OpenArea
{
    /** @var array<string, true> */
    private readonly array $blocked;

    public function __construct(private readonly GameState $state)
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
        $this->blocked = $blocked;
    }

    /** Number of squares reachable from $start, $start included. */
    public function from(Coordinate $start): int
    {
        if (! $this->state->board->contains($start) || isset($this->blocked[self::key($start)])) {
            return 0;
        }

        $seen = [self::key($start) => true];
        $queue = [$start];

        while ($queue !== []) {
            $square = \array_shift($queue);
            foreach (Direction::cases() as $direction) {
                $next = $square->adjacent($direction);
                $key = self::key($next);
                if (isset($seen[$key]) || isset($this->blocked[$key]) || ! $this->state->board->contains($next)) {
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
