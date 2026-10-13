<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Platform settings, administrators, member profiles';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE setting (value TEXT DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(64) NOT NULL, PRIMARY KEY (name))');
        $this->addSql('ALTER TABLE "user" ADD admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD avatar_file VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD bio VARCHAR(280) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD gamer_tags JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE setting');
        $this->addSql('ALTER TABLE "user" DROP admin');
        $this->addSql('ALTER TABLE "user" DROP avatar_file');
        $this->addSql('ALTER TABLE "user" DROP bio');
        $this->addSql('ALTER TABLE "user" DROP gamer_tags');
    }
}
