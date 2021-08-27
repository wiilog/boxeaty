<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\BoxType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210728142638 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('box_type');
        if ($table->hasColumn('volume')) {
            $this->addSql("
                UPDATE box_type
                SET box_type.volume = '" . BoxType::DEFAULT_VOLUME . "'
                WHERE box_type.volume IS NULL
            ");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
