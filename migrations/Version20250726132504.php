<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726132504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_entreprise (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, company_address LONGTEXT NOT NULL, company_email VARCHAR(255) NOT NULL, tva_applicable TINYINT(1) NOT NULL, default_currency VARCHAR(255) NOT NULL, vat_number VARCHAR(255) DEFAULT NULL, tva_rate SMALLINT NOT NULL, logo_path VARCHAR(255) DEFAULT NULL, invoice_prefix VARCHAR(50) DEFAULT NULL, company_phone VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_entreprise');
    }
}
