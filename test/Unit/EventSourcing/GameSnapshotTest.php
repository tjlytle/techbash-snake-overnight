<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\EventSourcing;

use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Events\End;
use BattleSnake\EventSourcing\Events\Move;
use BattleSnake\EventSourcing\Events\Nudge;
use BattleSnake\EventSourcing\Events\Start;
use BattleSnake\EventSourcing\Events\Turn;
use BattleSnake\EventSourcing\Game;
use BattleSnake\EventSourcing\GameId;
use BattleSnake\Tests\SnekSpec\Parser as SnekSpecParser;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A snapshot holds everything the handlers read from a replayed Game, so a
 * Game rebuilt from a snapshot plus the later events equals a full replay.
 */
final class GameSnapshotTest extends TestCase
{
    private static function aState(): GameState
    {
        $ascii = <<<BOARD
            -----
            --S--
            --t--
            -----
            BOARD;
        return GameStateParserFactory::make()->parse((new SnekSpecParser())->parse($ascii));
    }

    /**
     * @param list<object> $events
     * @return Generator<int, object, void, int>
     */
    private static function stream(array $events, int $lastVersion): Generator
    {
        yield from $events;
        return $lastVersion;
    }

    #[Test]
    public function a_snapshot_plus_later_events_matches_a_full_replay(): void
    {
        $state = self::aState();
        $id = GameId::generate();
        $before = [new Start($state), new Turn($state), new Move(Direction::UP), new Turn($state), new Move(Direction::LEFT)];
        $after = [new Nudge(Direction::DOWN), new Turn($state), new Move(Direction::DOWN), new Nudge(Direction::RIGHT)];

        $snapshot = Game::reconstituteFromEvents($id, self::stream($before, 5))->createSnapshot();
        $fromSnapshot = Game::reconstituteFromSnapshotAndEvents($snapshot, self::stream($after, 9));
        $fullReplay = Game::reconstituteFromEvents($id, self::stream([...$before, ...$after], 9));

        self::assertSame(5, $snapshot->aggregateRootVersion());
        self::assertSame(9, $fromSnapshot->aggregateRootVersion());
        self::assertSame($fullReplay->aggregateRootId()->toString(), $fromSnapshot->aggregateRootId()->toString());
        self::assertSame(3, $fromSnapshot->turn);
        self::assertTrue($fromSnapshot->is_started);
        self::assertFalse($fromSnapshot->is_finished);
        self::assertSame(Direction::DOWN, $fromSnapshot->last_move);
        self::assertSame(Direction::RIGHT, $fromSnapshot->last_nudge);
        self::assertEquals($fullReplay->createSnapshot(), $fromSnapshot->createSnapshot());
    }

    #[Test]
    public function a_snapshot_with_no_later_events_keeps_its_version_and_state(): void
    {
        $state = self::aState();
        $id = GameId::generate();
        $events = [new Start($state), new Turn($state), new Move(Direction::RIGHT), new Nudge(Direction::UP), new End($state)];

        $snapshot = Game::reconstituteFromEvents($id, self::stream($events, 5))->createSnapshot();
        $game = Game::reconstituteFromSnapshotAndEvents($snapshot, self::stream([], 0));

        self::assertSame(5, $game->aggregateRootVersion());
        self::assertTrue($game->is_finished);
        self::assertSame(1, $game->turn);
        self::assertSame(Direction::RIGHT, $game->last_move);
        self::assertSame(Direction::UP, $game->last_nudge);
    }

    #[Test]
    public function the_snapshot_state_is_plain_data(): void
    {
        $state = self::aState();
        $game = Game::reconstituteFromEvents(
            GameId::generate(),
            self::stream([new Start($state), new Turn($state), new Move(Direction::LEFT)], 3),
        );

        $snapshot = $game->createSnapshot();

        self::assertInstanceOf(Snapshot::class, $snapshot);
        self::assertSame(
            ['is_started' => true, 'is_finished' => false, 'turn' => 1, 'last_nudge' => null, 'last_move' => 'left'],
            $snapshot->state(),
        );
    }
}
