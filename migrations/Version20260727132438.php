<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: actors, projects, tickets, comments, audit journal,
 * attachments and API tokens.
 */
final class Version20260727132438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial tsutsumbi schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE actor (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(10) NOT NULL, email VARCHAR(180) DEFAULT NULL, roles JSON DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, slug VARCHAR(60) DEFAULT NULL, label VARCHAR(120) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_447556F9E7927C74 (email), UNIQUE INDEX UNIQ_447556F9989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE api_token (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(120) NOT NULL, agent_slug VARCHAR(60) NOT NULL, scopes JSON NOT NULL, selector VARCHAR(12) NOT NULL, verifier_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_7BA2F5EBB03A8386 (created_by_id), UNIQUE INDEX uniq_api_token_selector (selector), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, storage_path VARCHAR(255) NOT NULL, checksum VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, uploaded_by_id INT NOT NULL, INDEX IDX_795FD9BBA2B28FE8 (uploaded_by_id), INDEX idx_attachment_ticket (ticket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, `key` VARCHAR(10) NOT NULL, name VARCHAR(120) NOT NULL, path VARCHAR(500) NOT NULL, description LONGTEXT DEFAULT NULL, last_ticket_number INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, archived_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_project_path (path), UNIQUE INDEX uniq_project_key (`key`), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, resolution VARCHAR(20) DEFAULT NULL, priority VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, project_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_97A0ADA3166D1F9C (project_id), INDEX IDX_97A0ADA3B03A8386 (created_by_id), INDEX idx_ticket_status (status), INDEX idx_ticket_updated_at (updated_at), FULLTEXT INDEX ft_ticket_search (title, description), UNIQUE INDEX uniq_ticket_project_number (project_id, number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket_comment (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, ticket_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_98B80B3EF675F31B (author_id), INDEX idx_comment_ticket (ticket_id), FULLTEXT INDEX ft_comment_body (body), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket_event (id INT AUTO_INCREMENT NOT NULL, field VARCHAR(40) NOT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, actor_id INT NOT NULL, INDEX IDX_139E692C10DAF24A (actor_id), INDEX idx_event_ticket (ticket_id), INDEX idx_event_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBB03A8386 FOREIGN KEY (created_by_id) REFERENCES actor (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BBA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES actor (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES actor (id)');
        $this->addSql('ALTER TABLE ticket_comment ADD CONSTRAINT FK_98B80B3E700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket_comment ADD CONSTRAINT FK_98B80B3EF675F31B FOREIGN KEY (author_id) REFERENCES actor (id)');
        $this->addSql('ALTER TABLE ticket_event ADD CONSTRAINT FK_139E692C700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket_event ADD CONSTRAINT FK_139E692C10DAF24A FOREIGN KEY (actor_id) REFERENCES actor (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_7BA2F5EBB03A8386');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB700047D2');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BBA2B28FE8');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3166D1F9C');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket_comment DROP FOREIGN KEY FK_98B80B3E700047D2');
        $this->addSql('ALTER TABLE ticket_comment DROP FOREIGN KEY FK_98B80B3EF675F31B');
        $this->addSql('ALTER TABLE ticket_event DROP FOREIGN KEY FK_139E692C700047D2');
        $this->addSql('ALTER TABLE ticket_event DROP FOREIGN KEY FK_139E692C10DAF24A');
        $this->addSql('DROP TABLE actor');
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ticket_comment');
        $this->addSql('DROP TABLE ticket_event');
    }
}
