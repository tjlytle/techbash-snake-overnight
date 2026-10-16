<?php

declare(strict_types=1);

namespace BattleSnake\EventSourcing;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

/**
 * The Game repository the handlers get: retrieve() starts from the newest
 * snapshot and replays only the events after it, and persist() takes a fresh
 * snapshot every $every turns. Without it, turn 400 replays 400 boards.
 *
 * Snapshots are a cache: the event store keeps every event, and dropping the
 * snapshots table only makes the next request replay in full.
 *
 * @implements AggregateRootRepository<Game>
 */
final readonly class SnapshottingGameRepository implements AggregateRootRepository
{
    /**
     * @param AggregateRootRepositoryWithSnapshotting<Game> $games
     */
    public function __construct(
        private AggregateRootRepositoryWithSnapshotting $games,
        private int $every = 10,
    ) {
    }

    public function retrieve(AggregateRootId $aggregateRootId): object
    {
        return $this->games->retrieveFromSnapshot($aggregateRootId);
    }

    public function persist(object $aggregateRoot): void
    {
        assert($aggregateRoot instanceof Game);

        $this->games->persist($aggregateRoot);

        // A nudge or the end within a snapshot turn re-takes it; harmless, and current.
        if ($aggregateRoot->turn > 0 && $aggregateRoot->turn % $this->every === 0) {
            $this->games->storeSnapshot($aggregateRoot);
        }
    }

    public function persistEvents(AggregateRootId $aggregateRootId, int $aggregateRootVersion, object ...$events): void
    {
        $this->games->persistEvents($aggregateRootId, $aggregateRootVersion, ...$events);
    }
}
