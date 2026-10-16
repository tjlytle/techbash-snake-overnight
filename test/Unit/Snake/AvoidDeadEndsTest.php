<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Snake;

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\AvoidDeadEnds;
use BattleSnake\Strategy\Strategy;
use BattleSnake\Tests\SnekSpec\Parser;
use BattleSnake\Tests\Support\TestGame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvoidDeadEndsTest extends TestCase
{
    private static function board(string $ascii): GameState
    {
        return GameStateParserFactory::make()->parse((new Parser())->parse($ascii));
    }

    /** The preferred strategy always answers $direction. */
    private static function avoid(Direction $preferred, GameState $state): Direction
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

        return (new AvoidDeadEnds($strategy, new MoveClassifier()))(TestGame::fresh(), $state);
    }

    #[Test]
    public function refuses_a_one_square_pocket_walled_by_our_body(): void
    {
        // game b6f0f963 turn 130: right is a pocket of one, up is the open board.
        $state = self::board(<<<BOARD
            -----0-----
            --------0--
            -----------
            -----------
            -0---------
            --------0--
            -----Ttt--0
            ---0---tt-0
            ----ttS-t--
            --0-ttttt--
            -------tt--
            BOARD);

        self::assertSame(Direction::UP, self::avoid(Direction::RIGHT, $state));
    }

    #[Test]
    public function keeps_the_preferred_move_when_it_has_room(): void
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

        self::assertSame(Direction::LEFT, self::avoid(Direction::LEFT, $state));
    }

    #[Test]
    public function counts_our_tail_as_free_next_turn(): void
    {
        // The corner left of the head is walled by our tail, which moves away next turn.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            Tt---------
            -S---------
            BOARD);

        self::assertSame(Direction::LEFT, self::avoid(Direction::LEFT, $state));
    }

    #[Test]
    public function refuses_a_pocket_bigger_than_one_but_shorter_than_us(): void
    {
        // Length 14 above an empty bottom row of 11: down fits only part of us.
        $state = self::board(<<<BOARD
            -----------
            -----------
            -----------
            -----------
            -----------
            -----------
            ----------T
            ----------t
            ----------t
            Stttttttttt
            -----------
            BOARD);

        self::assertSame(Direction::UP, self::avoid(Direction::DOWN, $state));
    }
}
