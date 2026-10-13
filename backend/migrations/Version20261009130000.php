<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renames families to clans, and the parent/child roles to leader/member, keeping existing data.
 */
final class Version20261009130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename family to clan, parent/child roles to leader/member';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE family RENAME TO clan');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN family_id TO clan_id');
        $this->addSql('ALTER INDEX idx_8d93d649c35e566a RENAME TO IDX_8D93D649BEAF84C8');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN family_role TO clan_role');
        $this->addSql("UPDATE \"user\" SET clan_role = CASE clan_role WHEN 'parent' THEN 'leader' WHEN 'child' THEN 'member' ELSE clan_role END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE \"user\" SET clan_role = CASE clan_role WHEN 'leader' THEN 'parent' WHEN 'member' THEN 'child' ELSE clan_role END");
        $this->addSql('ALTER TABLE "user" RENAME COLUMN clan_role TO family_role');
        $this->addSql('ALTER INDEX IDX_8D93D649BEAF84C8 RENAME TO idx_8d93d649c35e566a');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN clan_id TO family_id');
        $this->addSql('ALTER TABLE clan RENAME TO family');
    }
}
