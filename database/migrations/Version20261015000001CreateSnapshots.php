<?php

declare(strict_types=1);

namespace BattleSnake\Migrations;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015000001CreateSnapshots extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the snapshots table: the newest Game snapshot per game, so /move stays fast in long games.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('snapshots');
        $table->addColumn('aggregate_root_id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('aggregate_root_version', 'integer', ['notnull' => true]);
        $table->addColumn('state', 'text', ['notnull' => true]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('aggregate_root_id')
                ->create(),
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('snapshots');
    }
}
