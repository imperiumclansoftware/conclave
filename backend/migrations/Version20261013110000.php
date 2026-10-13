<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coat of arms of the branches';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch ADD crest VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch DROP crest');
    }
}
