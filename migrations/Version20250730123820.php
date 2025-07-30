<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730123820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logs_ips ADD log_id INT NOT NULL');
        $this->addSql('ALTER TABLE logs_ips ADD CONSTRAINT FK_6404547EA675D86 FOREIGN KEY (log_id) REFERENCES logs (id)');
        $this->addSql('CREATE INDEX IDX_6404547EA675D86 ON logs_ips (log_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logs_ips DROP FOREIGN KEY FK_6404547EA675D86');
        $this->addSql('DROP INDEX IDX_6404547EA675D86 ON logs_ips');
        $this->addSql('ALTER TABLE logs_ips DROP log_id');
    }
}
