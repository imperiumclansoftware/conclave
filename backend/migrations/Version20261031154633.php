<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Text\RichText;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * News and event descriptions become HTML (editor): the plain texts written before are turned
 * into paragraphs, their line breaks kept. A text already in HTML is left as it is.
 */
final class Version20261031154633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'News and event descriptions in HTML (editor)';
    }

    public function up(Schema $schema): void
    {
        $this->convert('news', 'body');
        $this->convert('event', 'description');
    }

    public function down(Schema $schema): void
    {
        foreach ([['news', 'body'], ['event', 'description']] as [$table, $column]) {
            foreach ($this->connection->fetchAllAssociative("SELECT id, $column AS text FROM $table WHERE $column IS NOT NULL") as $row) {
                $this->addSql("UPDATE $table SET $column = ? WHERE id = ?", [RichText::plain($row['text']), $row['id']]);
            }
        }
    }

    private function convert(string $table, string $column): void
    {
        foreach ($this->connection->fetchAllAssociative("SELECT id, $column AS text FROM $table WHERE $column IS NOT NULL") as $row) {
            if (str_starts_with(ltrim((string) $row['text']), '<')) {
                continue;
            }
            $this->addSql("UPDATE $table SET $column = ? WHERE id = ?", [RichText::fromPlainText((string) $row['text']), $row['id']]);
        }
    }
}
