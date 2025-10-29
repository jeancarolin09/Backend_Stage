<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017112522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, organizer_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, event_date DATE NOT NULL, event_time TIME NOT NULL, event_location VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_3BAE0AA7876C4DDA (organizer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_option (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, type VARCHAR(20) NOT NULL, INDEX IDX_681F77E271F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, name VARCHAR(180) DEFAULT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used TINYINT(1) NOT NULL, INDEX IDX_F11D61A271F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rsvp (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, user_id INT DEFAULT NULL, invitation_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9FA5CE4E71F7E88B (event_id), INDEX IDX_9FA5CE4EA76ED395 (user_id), INDEX IDX_9FA5CE4EA35D7AF0 (invitation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, event_option_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5A10856471F7E88B (event_id), INDEX IDX_5A10856421512352 (event_option_id), INDEX IDX_5A108564A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE event_option ADD CONSTRAINT FK_681F77E271F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A271F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rsvp ADD CONSTRAINT FK_9FA5CE4E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rsvp ADD CONSTRAINT FK_9FA5CE4EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rsvp ADD CONSTRAINT FK_9FA5CE4EA35D7AF0 FOREIGN KEY (invitation_id) REFERENCES invitation (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A10856471F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A10856421512352 FOREIGN KEY (event_option_id) REFERENCES event_option (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7876C4DDA');
        $this->addSql('ALTER TABLE event_option DROP FOREIGN KEY FK_681F77E271F7E88B');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A271F7E88B');
        $this->addSql('ALTER TABLE rsvp DROP FOREIGN KEY FK_9FA5CE4E71F7E88B');
        $this->addSql('ALTER TABLE rsvp DROP FOREIGN KEY FK_9FA5CE4EA76ED395');
        $this->addSql('ALTER TABLE rsvp DROP FOREIGN KEY FK_9FA5CE4EA35D7AF0');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A10856471F7E88B');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A10856421512352');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_option');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE rsvp');
        $this->addSql('DROP TABLE vote');
    }
}
