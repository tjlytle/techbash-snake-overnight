<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Snake;

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\OpeningHunt;
use BattleSnake\Strategy\Strategy;
use BattleSnake\Tests\SnekSpec\Parser;
use BattleSnake\Tests\Support\TestGame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpeningHuntTest extends TestCase
{
    private static function board(string $ascii): GameState
    {
        return GameStateParserFactory::make()->parse((new Parser())->parse($ascii));
    }

    /** The preferred strategy always answers $direction. */
    private static function hunt(Direction $preferred, GameState $state): Direction
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

        return (new OpeningHunt($strategy, new MoveClassifier()))(TestGame::fresh(), $state);
    }

    #[Test]
    public function steps_toward_food_four_squares_away(): void
    {
        // game 62a9ed5a turn 6: W is as long as us and as far from the food; it does not count.
        $state = self::board(<<<BOARD
            --------U--
            --------v--
            --------V--
            -----------
            -----------
            -----0-----
            -----------
            -------W---
            -------x---
            ----tS-x---
            ---Tt--X---
            BOARD);

        self::assertSame(Direction::UP, self::hunt(Direction::DOWN, $state));
    }

    #[Test]
    public function leaves_food_a_longer_head_is_nearer_to(): void
    {
        // U (length 5) is one square from the food, we are four.
        $state = self::board(<<<BOARD
            -----------
            -----------
            ---Vvvv----
            -----0U----
            -----------
            -----------
            -----------
            -----S-----
            -----t-----
            -----t-----
            -----T-----
            BOARD);

        self::assertSame(Direction::LEFT, self::hunt(Direction::LEFT, $state));
    }

    #[Test]
    public function heads_for_the_food_no_longer_head_is_nearer_to(): void
    {
        // The food up is U's (length 5, one square away); the food down-right is ours.
        $state = self::board(<<<BOARD
            -----------
            -----------
            ---Vvvv----
            -----0U----
            -----------
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            --------0--
            BOARD);

        self::assertSame(Direction::RIGHT, self::hunt(Direction::LEFT, $state));
    }

    #[Test]
    public function does_not_follow_food_into_a_pocket_smaller_than_us(): void
    {
        // The food sits in a two-square pocket walled in by U; we are four long.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            ----vvv----
            ----v0v----
            ---Uv-vV---
            -----S-----
            -----t-----
            -----t-----
            -----T-----
            BOARD);

        self::assertSame(Direction::LEFT, self::hunt(Direction::LEFT, $state));
    }

    #[Test]
    public function with_no_food_the_preferred_answer_stands(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::RIGHT, self::hunt(Direction::RIGHT, $state));
    }
}
