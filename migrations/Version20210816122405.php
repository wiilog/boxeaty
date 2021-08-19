<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210816122405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update for StatusTrait';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE collect DROP CONSTRAINT FK_A40662F46BF700BD');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC106BF700BD');
        $this->addSql('ALTER TABLE collect CHANGE status_id status_id INT NOT NULL');
        $this->addSql('ALTER TABLE delivery CHANGE status_id status_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
