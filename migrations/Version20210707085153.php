<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210707085153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable("box")->hasColumn("is_box")) {
            $this->addSql('ALTER TABLE box ADD is_box TINYINT(1) NOT NULL DEFAULT 1 ;');
        }
    }


}
