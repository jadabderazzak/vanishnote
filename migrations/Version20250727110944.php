<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250727110944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachements (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, filename VARCHAR(255) NOT NULL, filepath VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, uploaded_at DATETIME NOT NULL, encryption_metadata VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, deleted_at DATETIME NOT NULL, INDEX IDX_212B82DC26ED0855 (note_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachements ADD CONSTRAINT FK_212B82DC26ED0855 FOREIGN KEY (note_id) REFERENCES notes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachements DROP FOREIGN KEY FK_212B82DC26ED0855');
        $this->addSql('DROP TABLE attachements');
    }
}
