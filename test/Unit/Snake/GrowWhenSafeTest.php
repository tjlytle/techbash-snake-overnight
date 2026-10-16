<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Snake;

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\GrowWhenSafe;
use BattleSnake\Strategy\Strategy;
use BattleSnake\Tests\SnekSpec\Parser;
use BattleSnake\Tests\Support\TestGame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrowWhenSafeTest extends TestCase
{
    private static function board(string $ascii): GameState
    {
        return GameStateParserFactory::make()->parse((new Parser())->parse($ascii));
    }

    /** The preferred strategy always answers $direction. */
    private static function grow(Direction $preferred, GameState $state): Direction
    {
        $strategy = new class ($preferred) implements Strategy {
            public function __construct(private readonly Direction $direction)
            {
            }

            public function __invoke(Game $game, GameState $state): Direction
            {
                return $this->direction;
            }
        };

        return (new GrowWhenSafe($strategy, new MoveClassifier()))(TestGame::fresh(), $state);
    }

    #[Test]
    public function steps_onto_food_next_to_the_head(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -----------
            -----S0----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::RIGHT, self::grow(Direction::LEFT, $state));
    }

    #[Test]
    public function steps_toward_food_two_squares_away(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----0-----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::UP, self::grow(Direction::LEFT, $state));
    }

    #[Test]
    public function leaves_food_three_squares_away_to_the_preferred_strategy(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----0-----
            -----------
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::LEFT, self::grow(Direction::LEFT, $state));
    }

    #[Test]
    public function leaves_food_a_longer_head_is_nearer_to(): void
    {
        // U (length 4) is one square from the food, we are two.
        $state = self::board(<<<BOARD
            -----------
            -----------
            ----Vvv----
            -----0U----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::LEFT, self::grow(Direction::LEFT, $state));
    }

    #[Test]
    public function takes_food_a_shorter_head_is_nearer_to(): void
    {
        // U (length 2) is one square from the food, we are two; the square we step onto is not
        // one U can reach.
        $state = self::board(<<<BOARD
            -----------
            -----V-----
            -----U-----
            -----0-----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::UP, self::grow(Direction::RIGHT, $state));
    }

    #[Test]
    public function never_steps_onto_a_square_another_head_can_reach(): void
    {
        // The food is next to us and next to U's head: the move is DANGER, not SAFE.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -------V---
            -----S0U---
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::LEFT, self::grow(Direction::LEFT, $state));
    }

    #[Test]
    public function between_two_foods_prefers_the_square_off_the_edge(): void
    {
        // game 310cb986 turn 74: food up and food left; left lands on the edge.
        $state = self::board(<<<BOARD
            -----------
            ---T-------
            -0-ttt-----
            0St--t--0--
            --tttt-----
            --------0--
            -0-0---0---
            ---0-0-----
            -----------
            --0--------
            0----------
            BOARD);

        self::assertSame(Direction::UP, self::grow(Direction::DOWN, $state));
    }
}
