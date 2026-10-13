<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Single clan, sign-up approval, e-mail addresses';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD status VARCHAR(10) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" DROP admin');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        // The app now serves a single clan: everyone joins the oldest one (renamable in the administration)
        $this->addSql('UPDATE "user" SET clan_id = (SELECT MIN(id) FROM clan)');
        $this->addSql('UPDATE video_call SET clan_id = (SELECT MIN(id) FROM clan)');
        $this->addSql('DELETE FROM clan WHERE id <> (SELECT MIN(id) FROM clan)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE "user" ADD admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP email');
        $this->addSql('ALTER TABLE "user" DROP status');
        $this->addSql('ALTER TABLE "user" DROP approved_at');
    }
}
