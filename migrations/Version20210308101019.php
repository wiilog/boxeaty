<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210308101019 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('RENAME TABLE tracking_movement TO box_record');
        $this->addSql('ALTER TABLE box_record ADD tracking_movement TINYINT(1) DEFAULT 1 NOT NULL;');
        $this->addSql('INSERT INTO box_record (box_id, location_id, quality_id, client_id, user_id, `date`, `state`, comment, tracking_movement)
                            SELECT box_id, location_id, quality_id, client_id, user_id, `date`, `state`, comment, 0
                            FROM box_record');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
