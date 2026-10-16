<?php

declare(strict_types=1);

namespace BattleSnake\EventSourcing;

use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use EventSauce\EventSourcing\Snapshotting\SnapshotRepository;

/**
 * Keeps the newest snapshot of each game in one row of the `snapshots` table,
 * next to the event store on the same connection. The state is JSON, so it
 * must be plain data (Game::createSnapshotState makes sure of that).
 */
final readonly class DbalSnapshotRepository implements SnapshotRepository
{
    public function __construct(
        private Connection $connection,
        private string $tableName = 'snapshots',
    ) {
    }

    public function persist(Snapshot $snapshot): void
    {
        $row = [
            'aggregate_root_id' => $snapshot->aggregateRootId()->toString(),
            'aggregate_root_version' => $snapshot->aggregateRootVersion(),
            'state' => json_encode($snapshot->state(), JSON_THROW_ON_ERROR),
        ];

        // Delete then insert rather than update-or-insert: MySQL reports an
        // update that changes nothing as 0 rows, which would look like a miss.
        $this->connection->transactional(function (Connection $db) use ($row): void {
            $db->delete($this->tableName, ['aggregate_root_id' => $row['aggregate_root_id']]);
            $db->insert($this->tableName, $row);
        });
    }

    public function retrieve(AggregateRootId $id): ?Snapshot
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT aggregate_root_version, state FROM %s WHERE aggregate_root_id = ?', $this->tableName),
            [$id->toString()],
        );

        if ($row === false) {
            return null;
        }

        return new Snapshot(
            $id,
            (int) $row['aggregate_root_version'],
            json_decode((string) $row['state'], true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
