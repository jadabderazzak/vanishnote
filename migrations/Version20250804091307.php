<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250804091307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secure_files ADD user_id INT NOT NULL, ADD aad VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE secure_files ADD CONSTRAINT FK_83526E31A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_83526E31A76ED395 ON secure_files (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secure_files DROP FOREIGN KEY FK_83526E31A76ED395');
        $this->addSql('DROP INDEX IDX_83526E31A76ED395 ON secure_files');
        $this->addSql('ALTER TABLE secure_files DROP user_id, DROP aad');
    }
}
