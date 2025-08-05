<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250805193131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_entreprise (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, company_address LONGTEXT NOT NULL, company_email VARCHAR(255) NOT NULL, tva_applicable TINYINT(1) NOT NULL, default_currency VARCHAR(255) NOT NULL, vat_number VARCHAR(255) DEFAULT NULL, tva_rate SMALLINT NOT NULL, logo_path VARCHAR(255) DEFAULT NULL, invoice_prefix VARCHAR(50) DEFAULT NULL, company_phone VARCHAR(255) DEFAULT NULL, show_logo_on_invoice TINYINT(1) DEFAULT NULL, no_reply_email VARCHAR(255) NOT NULL, support_email VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE api_credential (id INT AUTO_INCREMENT NOT NULL, secret_key_encrypted LONGTEXT NOT NULL, public_key_encrypted LONGTEXT DEFAULT NULL, service VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, webhook_secret_encrypted VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE attachements (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, filename VARCHAR(255) NOT NULL, filepath VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, uploaded_at DATETIME NOT NULL, encryption_metadata VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_212B82DC26ED0855 (note_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, country_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, company VARCHAR(255) DEFAULT NULL, is_company TINYINT(1) NOT NULL, company_adress VARCHAR(255) DEFAULT NULL, vat_number VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, number_notes_created INT NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_C7440455A76ED395 (user_id), INDEX IDX_C7440455F92F3E70 (country_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE country (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(5) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE currency (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, symbol VARCHAR(10) NOT NULL, is_primary TINYINT(1) NOT NULL, code VARCHAR(10) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE logs (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, user_id INT NOT NULL, deleted_at DATETIME DEFAULT NULL, ip_adress VARCHAR(255) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, additionnal_data LONGTEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, note_title VARCHAR(50) DEFAULT NULL, INDEX IDX_F08FC65C26ED0855 (note_id), INDEX IDX_F08FC65CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE logs_ips (id INT AUTO_INCREMENT NOT NULL, log_id INT NOT NULL, ip_adress VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, INDEX IDX_6404547EA675D86 (log_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notes (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, plan_type_id INT NOT NULL, title LONGTEXT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, slug VARCHAR(255) NOT NULL, character_count INT NOT NULL, encryption_metadata VARCHAR(255) NOT NULL, password VARCHAR(255) DEFAULT NULL, read_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, expiration_date DATETIME DEFAULT NULL, burn_after_reading TINYINT(1) DEFAULT NULL, burned TINYINT(1) DEFAULT NULL, minutes SMALLINT DEFAULT NULL, INDEX IDX_11BA68CA76ED395 (user_id), INDEX IDX_11BA68C7BF3C49B (plan_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, subscription_plan_id INT NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(15) NOT NULL, status VARCHAR(30) NOT NULL, months SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, slug VARCHAR(255) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, tva SMALLINT NOT NULL, invoice_ref VARCHAR(255) DEFAULT NULL, invoice_id INT NOT NULL, INDEX IDX_6D28840DA76ED395 (user_id), INDEX IDX_6D28840D9B8CE200 (subscription_plan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE secure_files (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, filename VARCHAR(255) NOT NULL, filepath VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, uploaded_at DATETIME NOT NULL, encryption_metadata VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, aad VARCHAR(255) NOT NULL, INDEX IDX_83526E31A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subscription_plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, features JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', slug VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, is_active TINYINT(1) NOT NULL, number_notes INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subscriptions (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, subscription_plan_id INT NOT NULL, status TINYINT(1) NOT NULL, started_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, notes_created SMALLINT NOT NULL, created_at DATETIME NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_4778A01A76ED395 (user_id), INDEX IDX_4778A019B8CE200 (subscription_plan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE system_log (id INT AUTO_INCREMENT NOT NULL, level SMALLINT NOT NULL, message LONGTEXT NOT NULL, logged_at DATETIME NOT NULL, is_handled TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, has_access TINYINT(1) NOT NULL, ip VARCHAR(46) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachements ADD CONSTRAINT FK_212B82DC26ED0855 FOREIGN KEY (note_id) REFERENCES notes (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE logs ADD CONSTRAINT FK_F08FC65C26ED0855 FOREIGN KEY (note_id) REFERENCES notes (id)');
        $this->addSql('ALTER TABLE logs ADD CONSTRAINT FK_F08FC65CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE logs_ips ADD CONSTRAINT FK_6404547EA675D86 FOREIGN KEY (log_id) REFERENCES logs (id)');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68C7BF3C49B FOREIGN KEY (plan_type_id) REFERENCES subscription_plan (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9B8CE200 FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plan (id)');
        $this->addSql('ALTER TABLE secure_files ADD CONSTRAINT FK_83526E31A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A019B8CE200 FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plan (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachements DROP FOREIGN KEY FK_212B82DC26ED0855');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455A76ED395');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455F92F3E70');
        $this->addSql('ALTER TABLE logs DROP FOREIGN KEY FK_F08FC65C26ED0855');
        $this->addSql('ALTER TABLE logs DROP FOREIGN KEY FK_F08FC65CA76ED395');
        $this->addSql('ALTER TABLE logs_ips DROP FOREIGN KEY FK_6404547EA675D86');
        $this->addSql('ALTER TABLE notes DROP FOREIGN KEY FK_11BA68CA76ED395');
        $this->addSql('ALTER TABLE notes DROP FOREIGN KEY FK_11BA68C7BF3C49B');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9B8CE200');
        $this->addSql('ALTER TABLE secure_files DROP FOREIGN KEY FK_83526E31A76ED395');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A01A76ED395');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A019B8CE200');
        $this->addSql('DROP TABLE admin_entreprise');
        $this->addSql('DROP TABLE api_credential');
        $this->addSql('DROP TABLE attachements');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE currency');
        $this->addSql('DROP TABLE logs');
        $this->addSql('DROP TABLE logs_ips');
        $this->addSql('DROP TABLE notes');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE secure_files');
        $this->addSql('DROP TABLE subscription_plan');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE system_log');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
