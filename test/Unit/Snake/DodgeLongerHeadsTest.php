<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Snake;

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\DodgeLongerHeads;
use BattleSnake\Strategy\Strategy;
use BattleSnake\Tests\SnekSpec\Parser;
use BattleSnake\Tests\Support\TestGame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DodgeLongerHeadsTest extends TestCase
{
    private static function board(string $ascii): GameState
    {
        return GameStateParserFactory::make()->parse((new Parser())->parse($ascii));
    }

    /** The preferred strategy always answers $direction. */
    private static function dodge(Direction $preferred, GameState $state): Direction
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

        return (new DodgeLongerHeads($strategy, new MoveClassifier()))(TestGame::fresh(), $state);
    }

    #[Test]
    public function leaves_the_edge_that_runs_into_the_corner(): void
    {
        // game 20487baf turn 86: snork (U, length 10) two squares away; down runs into the corner.
        $state = self::board(<<<BOARD
            -----------
            --------0--
            -----------
            --0----vv--
            -------vv--
            ------Vvv--
            ---0-0vvU-T
            ----------t
            --0-------S
            -----------
            --------0--
            BOARD);

        self::assertSame(Direction::LEFT, self::dodge(Direction::DOWN, $state));
    }

    #[Test]
    public function leaves_the_top_edge_with_a_longer_head_below(): void
    {
        $state = self::board(<<<BOARD
            -----StT---
            -----------
            vvvU-------
            V----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::DOWN, self::dodge(Direction::LEFT, $state));
    }

    #[Test]
    public function refuses_a_square_a_longer_head_can_reach(): void
    {
        // U (length 4) can step onto the square above us.
        $state = self::board(<<<BOARD
            -----------
            -----V-----
            -----v-----
            -----v-----
            -----U-----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            BOARD);

        self::assertNotSame(Direction::UP, self::dodge(Direction::UP, $state));
    }

    #[Test]
    public function treats_an_equal_length_head_as_longer(): void
    {
        // Head to head with the same length, both snakes die.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----V-----
            -----v-----
            -----U-----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            BOARD);

        self::assertNotSame(Direction::UP, self::dodge(Direction::UP, $state));
    }

    #[Test]
    public function ignores_a_shorter_head(): void
    {
        // U is length 2: a head-to-head there is one we win.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----V-----
            -----U-----
            -----------
            -----S-----
            -----t-----
            -----T-----
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::UP, self::dodge(Direction::UP, $state));
    }

    #[Test]
    public function keeps_the_preferred_move_with_no_longer_head_close(): void
    {
        // U is longer but three squares away: the edge is ours to take.
        $state = self::board(<<<BOARD
            -----StT---
            -----------
            -----------
            vvU--------
            V----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            BOARD);

        self::assertSame(Direction::LEFT, self::dodge(Direction::LEFT, $state));
    }
}
