<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\BoxType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210809084915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO box_type (name, price, active, capacity, shape, volume)
            VALUES ('" . BoxType::STARTER_KIT . "',0.00,1,'500ml','Rectangle', '" . BoxType::DEFAULT_VOLUME . "')
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
