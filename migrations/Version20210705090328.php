<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210705090328 extends AbstractMigration {

    public function up(Schema $schema): void {
        if (!$schema->hasTable("counter_order")) {
            $this->addSql("ALTER TABLE `order` RENAME TO `counter_order`");
        }
    }

}
