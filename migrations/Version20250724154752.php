<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250724154752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscriptions ADD subscription_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A019B8CE200 FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plan (id)');
        $this->addSql('CREATE INDEX IDX_4778A019B8CE200 ON subscriptions (subscription_plan_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A019B8CE200');
        $this->addSql('DROP INDEX IDX_4778A019B8CE200 ON subscriptions');
        $this->addSql('ALTER TABLE subscriptions DROP subscription_plan_id');
    }
}
