<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230418164026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610876459BC');
        $this->addSql('ALTER TABLE sous_dossier DROP FOREIGN KEY FK_1E41987AA76ED395');
        $this->addSql('DROP TABLE sous_dossier');
        $this->addSql('DROP INDEX IDX_8C9F3610876459BC ON file');
        $this->addSql('ALTER TABLE file DROP sous_dossier_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sous_dossier (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, namedossier VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, datedossier DATE NOT NULL, status TINYINT(1) DEFAULT 1 NOT NULL, versionning TINYINT(1) DEFAULT 0, INDEX IDX_1E41987AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE sous_dossier ADD CONSTRAINT FK_1E41987AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE file ADD sous_dossier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610876459BC FOREIGN KEY (sous_dossier_id) REFERENCES sous_dossier (id)');
        $this->addSql('CREATE INDEX IDX_8C9F3610876459BC ON file (sous_dossier_id)');
    }
}
