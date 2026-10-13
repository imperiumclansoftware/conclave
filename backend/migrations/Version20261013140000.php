<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Branches activated by the leaders, one per coat of arms';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch ADD active BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX branch_clan_crest ON branch (clan_id, crest)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX branch_clan_crest');
        $this->addSql('ALTER TABLE branch DROP active');
    }
}
