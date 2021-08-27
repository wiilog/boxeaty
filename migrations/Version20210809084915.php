<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\BoxType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210809084915 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $name = BoxType::STARTER_KIT;
        $volume = BoxType::DEFAULT_VOLUME;
        $this->addSql('ALTER TABLE box_type ADD volume DECIMAL;');
        $this->addSql("
            UPDATE box_type
            SET box_type.volume = '" . BoxType::DEFAULT_VOLUME . "'
            WHERE box_type.volume IS NULL
        ");

        $existing = $this->connection->executeQuery("SELECT id FROM box_type WHERE name = '$name'")->rowCount();
        if (!$existing) {
            $this->addSql("
                INSERT INTO box_type (name, price, active, capacity, shape, volume)
                VALUES ('$name', 0.00, 1, '500ml', 'Rectangle', '$volume')
            ");
        }
    }

    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs
    }

}
