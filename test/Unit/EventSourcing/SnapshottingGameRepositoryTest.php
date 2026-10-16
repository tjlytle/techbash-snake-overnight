<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\EventSourcing;

use BattleSnake\Domain\Direction;
use BattleSnake\Domain\GameState;
use BattleSnake\Domain\Parser\GameStateParserFactory;
use BattleSnake\EventSourcing\Game;
use BattleSnake\EventSourcing\GameId;
use BattleSnake\Strategy\Strategy;
use BattleSnake\Tests\SnekSpec\Parser as SnekSpecParser;
use BattleSnake\Tests\Support\TestSchema;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRootRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The repository the handlers use, as wired in the container: it snapshots
 * every 10 turns and rebuilds a game from the newest snapshot plus the events
 * after it, so a long game does not replay every board on every /move.
 */
final class SnapshottingGameRepositoryTest extends TestCase
{
    private Connection $db;
    /** @var AggregateRootRepository<Game> */
    private AggregateRootRepository $games;
    private GameId $id;

    protected function setUp(): void
    {
        $container = TestSchema::application()->getContainer();
        $this->db = $container->get(Connection::class);
        $this->games = $container->get(AggregateRootRepository::class);
        $this->id = GameId::generate();
    }

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

    private static function always(Direction $direction): Strategy
    {
        return new class ($direction) implements Strategy {
            public function __construct(private readonly Direction $direction)
            {
            }

            public function __invoke($game, $state): Direction
            {
                return $this->direction;
            }
        };
    }

    /** Start the game and play $turns moves, one retrieve/persist per request like the handlers. */
    private function play(int $turns): void
    {
        $state = self::aState();
        $game = $this->games->retrieve($this->id);
        $game->start($state);
        $this->games->persist($game);

        for ($i = 1; $i <= $turns; $i++) {
            $game = $this->games->retrieve($this->id);
            $game->turn($state, self::always($i % 2 === 0 ? Direction::LEFT : Direction::UP));
            $this->games->persist($game);
        }
    }

    /** @return array{aggregate_root_version: int, state: string}|false */
    private function snapshotRow(): array|false
    {
        return $this->db->fetchAssociative(
            'SELECT aggregate_root_version, state FROM snapshots WHERE aggregate_root_id = ?',
            [$this->id->toString()],
        );
    }

    #[Test]
    public function no_snapshot_is_taken_before_turn_ten(): void
    {
        $this->play(9);

        self::assertFalse($this->snapshotRow());
        self::assertSame(9, $this->games->retrieve($this->id)->turn);
    }

    #[Test]
    public function a_snapshot_is_taken_every_ten_turns(): void
    {
        $this->play(25);

        $row = $this->snapshotRow();
        self::assertNotFalse($row);
        // Start + 20 × (Turn, Move): the snapshot from turn 20 replaced the one from turn 10.
        self::assertSame(41, (int) $row['aggregate_root_version']);
        self::assertSame(20, json_decode($row['state'], true)['turn']);
    }

    #[Test]
    public function retrieve_rebuilds_from_the_snapshot_and_only_the_later_events(): void
    {
        $this->play(25);

        // Remove every event the snapshot covers: a full replay could no longer
        // reach turn 25, so only snapshot + later events can.
        $this->db->executeStatement(
            'DELETE FROM domain_messages WHERE aggregate_root_id = ? AND version <= 41',
            [$this->id->toString()],
        );

        $game = $this->games->retrieve($this->id);
        self::assertSame(25, $game->turn);
        self::assertSame(51, $game->aggregateRootVersion());
        self::assertSame(Direction::UP, $game->last_move);
        self::assertTrue($game->is_started);
    }

    #[Test]
    public function the_event_store_still_holds_every_event(): void
    {
        $this->play(25);

        self::assertSame(51, (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM domain_messages WHERE aggregate_root_id = ?',
            [$this->id->toString()],
        ));
    }

    #[Test]
    public function a_game_continues_after_a_snapshot_without_version_clashes(): void
    {
        $this->play(10);

        $game = $this->games->retrieve($this->id);
        $game->nudge(Direction::RIGHT);
        $this->games->persist($game);
        $game = $this->games->retrieve($this->id);
        $game->end(self::aState());
        $this->games->persist($game);

        $game = $this->games->retrieve($this->id);
        self::assertTrue($game->is_finished);
        self::assertSame(Direction::RIGHT, $game->last_nudge);
        self::assertSame(23, $game->aggregateRootVersion());
    }
}
