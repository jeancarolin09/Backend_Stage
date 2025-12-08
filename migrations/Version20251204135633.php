<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204135633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_event (event_source INT NOT NULL, event_target INT NOT NULL, INDEX IDX_7AB5BB8B6D130821 (event_source), INDEX IDX_7AB5BB8B74F658AE (event_target), PRIMARY KEY(event_source, event_target)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_event ADD CONSTRAINT FK_7AB5BB8B6D130821 FOREIGN KEY (event_source) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_event ADD CONSTRAINT FK_7AB5BB8B74F658AE FOREIGN KEY (event_target) REFERENCES event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_event DROP FOREIGN KEY FK_7AB5BB8B6D130821');
        $this->addSql('ALTER TABLE event_event DROP FOREIGN KEY FK_7AB5BB8B74F658AE');
        $this->addSql('DROP TABLE event_event');
    }
}
