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
 * Keeps away from snakes that win a head-to-head (as long as us or longer). While none
 * has its head within two squares of ours (in each direction), the preferred answer
 * stands. Otherwise ranks the non-END moves:
 *
 *   1. not a square such a head can step onto next turn
 *   2. at least our length of open area (flood fill)
 *   3. not along an edge, toward a corner
 *   4. the larger open area
 *   5. the farther from the nearest such head
 *
 * Ties go to the preferred answer. With no move left but END it returns the preferred
 * answer, for Fallback to catch.
 */
final class DodgeLongerHeads implements Strategy
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
        $heads = $this->longerHeads($state);

        if ($heads === []) {
            return $preferred;
        }

        $length = $state->you->length;
        $area = new OpenArea($state);
        $best = null;
        $bestRank = null;

        foreach (($this->classifier)($state) as $move) {
            if ($move->is(Classification::END)) {
                continue;
            }

            $square = $state->you->head->adjacent($move->direction);
            $room = $area->from($square);
            $distance = \min(\array_map(static fn(Coordinate $head): int => self::distance($head, $square), $heads));
            $rank = [
                $distance <= 1 ? 1 : 0,
                $room >= $length ? 0 : 1,
                $this->alongEdge($state, $square, $move->direction) ? 1 : 0,
                -$room,
                -$distance,
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
     * Heads of the other snakes as long as us or longer, within RANGE squares of ours.
     *
     * @return list<Coordinate>
     */
    private function longerHeads(GameState $state): array
    {
        $you = $state->you;
        $heads = [];
        foreach ($state->board->snakes as $snake) {
            if ($snake->id === $you->id || $snake->head === null || $snake->length < $you->length) {
                continue;
            }
            $dx = \abs($snake->head->x - $you->head->x);
            $dy = \abs($snake->head->y - $you->head->y);
            if (\max($dx, $dy) <= self::RANGE) {
                $heads[] = $snake->head;
            }
        }
        return $heads;
    }

    /** True when $square is on an edge and $direction runs along it, toward a corner. */
    private function alongEdge(GameState $state, Coordinate $square, Direction $direction): bool
    {
        return match ($direction) {
            Direction::UP, Direction::DOWN => $square->x === 0 || $square->x === $state->board->width - 1,
            Direction::LEFT, Direction::RIGHT => $square->y === 0 || $square->y === $state->board->height - 1,
        };
    }

    private static function distance(Coordinate $a, Coordinate $b): int
    {
        return \abs($a->x - $b->x) + \abs($a->y - $b->y);
    }
}
