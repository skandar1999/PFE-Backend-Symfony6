<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230330000904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossier CHANGE file_id file_id INT NOT NULL, CHANGE namedossier namedossier VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE file ADD dossier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('CREATE INDEX IDX_8C9F3610611C0C56 ON file (dossier_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossier CHANGE file_id file_id INT DEFAULT NULL, CHANGE namedossier namedossier VARCHAR(255) DEFAULT \'\'');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610611C0C56');
        $this->addSql('DROP INDEX IDX_8C9F3610611C0C56 ON file');
        $this->addSql('ALTER TABLE file DROP dossier_id');
    }
}
