<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210412103640 extends AbstractMigration {

    public function getDescription(): string {
        return "Add creation date on boxes";
    }

    public function up(Schema $schema): void {
        if(!$schema->getTable("box")->hasColumn("creation_date")) {
            $this->addSql('ALTER TABLE box ADD creation_date DATETIME');
            $this->addSql('
                UPDATE box
                INNER JOIN (
                    SELECT MIN(box_record.date) AS date,
                           box_record.box_id AS box_id
                    FROM box_record
                    GROUP BY box_record.box_id
                ) AS box_record_ ON box_record_.box_id = box.id
                SET box.creation_date = box_record_.date
                WHERE box.creation_date IS NULL
            ');
            $this->addSql('
                UPDATE box
                SET box.creation_date = NOW()
                WHERE box.creation_date IS NULL
            ');
        }
    }

}
