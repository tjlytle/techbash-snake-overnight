<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Support;

use BattleSnake\Core\Application;
use BattleSnake\Core\ApplicationFactory;
use BattleSnake\Core\Config;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;

/**
 * An Application on an in-memory SQLite database with the event-store,
 * snapshot and projection tables created, for tests that drive the HTTP stack in-process.
 */
final class TestSchema
{
    public static function application(): Application
    {
        $app = (new ApplicationFactory())->make([
            [Config::class => \DI\factory(static fn() => new Config('sqlite:///:memory:'))],
        ]);
        self::create($app->getContainer()->get(Connection::class));

        return $app;
    }

    public static function create(Connection $conn): void
    {
        $platform = $conn->getDatabasePlatform();

        $schema = new Schema();
        $messages = $schema->createTable('domain_messages');
        $messages->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $messages->addColumn('event_id', 'string', ['length' => 36, 'notnull' => true]);
        $messages->addColumn('aggregate_root_id', 'string', ['length' => 36, 'notnull' => true]);
        $messages->addColumn('version', 'integer', ['notnull' => true]);
        $messages->addColumn('payload', 'text', ['notnull' => true]);
        $messages->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $messages->addUniqueIndex(['aggregate_root_id', 'version']);

        $snapshots = $schema->createTable('snapshots');
        $snapshots->addColumn('aggregate_root_id', 'string', ['length' => 36, 'notnull' => true]);
        $snapshots->addColumn('aggregate_root_version', 'integer', ['notnull' => true]);
        $snapshots->addColumn('state', 'text', ['notnull' => true]);
        $snapshots->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('aggregate_root_id')->create());

        $stats = $schema->createTable('game_stats');
        $stats->addColumn('aggregate_id', 'string', ['length' => 36, 'notnull' => true]);
        $stats->addColumn('win', 'boolean');
        $stats->addColumn('length', 'integer');
        $stats->addColumn('survived', 'integer');
        $stats->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('aggregate_id')->create());

        foreach ($schema->toSql($platform) as $sql) {
            $conn->executeStatement($sql);
        }
    }
}
