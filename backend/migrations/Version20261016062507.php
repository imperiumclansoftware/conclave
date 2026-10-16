<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261016062507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TV series beside the films (TMDB fiches)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_8244be2255bcc5e5');
        $this->addSql('ALTER TABLE film ADD end_date VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE film ADD ongoing BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE film ADD season_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE film ADD episode_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE film ADD kind VARCHAR(10) DEFAULT \'movie\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX film_kind_tmdb ON film (kind, tmdb_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX film_kind_tmdb');
        $this->addSql('ALTER TABLE film DROP end_date');
        $this->addSql('ALTER TABLE film DROP ongoing');
        $this->addSql('ALTER TABLE film DROP season_count');
        $this->addSql('ALTER TABLE film DROP episode_count');
        $this->addSql('ALTER TABLE film DROP kind');
        $this->addSql('CREATE UNIQUE INDEX uniq_8244be2255bcc5e5 ON film (tmdb_id)');
    }
}
