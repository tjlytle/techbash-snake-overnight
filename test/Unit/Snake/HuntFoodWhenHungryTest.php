<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Snake;

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\Snake\HuntFoodWhenHungry;
use BattleSnake\Tests\SnekSpec\Parser;
use BattleSnake\Tests\Support\TestGame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HuntFoodWhenHungryTest extends TestCase
{
    private static function board(string $ascii): GameState
    {
        return GameStateParserFactory::make()->parse((new Parser())->parse($ascii));
    }

    private static function hunt(bool $desperate, GameState $state): Direction
    {
        return (new HuntFoodWhenHungry(new MoveClassifier(), $desperate))(TestGame::fresh(), $state);
    }

    #[Test]
    public function steps_toward_the_nearest_food(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -----------
            -----S-0---
            -----t-----
            -----T-----
            -----------
            -0---------
            -----------
            BOARD);

        self::assertSame(Direction::RIGHT, self::hunt(false, $state));
    }

    #[Test]
    public function takes_contested_food_when_it_is_the_only_way_to_close_in(): void
    {
        // game cd37ef28 turn 99: both open moves are next to W's head; down is the food.
        $state = self::board(<<<BOARD
            ttT----vv--
            S-----Uvvv-
            0Wx-----0vv
            --x-------v
            --xx------v
            ---xxx----v
            0----X----V
            -----------
            ---0-------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::DOWN, self::hunt(false, $state));
    }

    #[Test]
    public function when_hungry_a_safe_approach_beats_contested_food(): void
    {
        // Food left of us is also next to U's head. Food two up is uncontested.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----0-----
            -----------
            ---U0S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::UP, self::hunt(false, $state));
    }

    #[Test]
    public function when_desperate_the_nearest_food_wins_even_if_contested(): void
    {
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----0-----
            -----------
            ---U0S-----
            -----t-----
            -----T-----
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::LEFT, self::hunt(true, $state));
    }
}
