<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210730132215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable("delivery_method")) {
            $this->addSql('ALTER TABLE delivery_method ADD type INTEGER NOT NULL');
            $deliveryMethods = $this->connection->executeQuery("SELECT * FROM delivery_method;");
            foreach ($deliveryMethods as $deliveryMethod) {
                switch ($deliveryMethod['icon']) {
                    case "bike":
                        $this->addSql("UPDATE delivery_method SET type = 0 WHERE id = ${deliveryMethod['id']}");
                        break;
                    case "light-truck":
                        $this->addSql("UPDATE delivery_method SET type = 1 WHERE id = ${deliveryMethod['id']}");
                        break;
                    case "heavy-truck":
                        $this->addSql("UPDATE delivery_method SET type = 2 WHERE id = ${deliveryMethod['id']}");
                        break;
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
