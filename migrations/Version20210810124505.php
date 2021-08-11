<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Status;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210810124505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renaming "A valider" client order status into "A valider BoxEaty".';
    }

    public function up(Schema $schema): void
    {
        $orderToValidateBoxeaty = Status::CODE_ORDER_TO_VALIDATE_BOXEATY;
        $this->addSql("UPDATE status SET status.code = '$orderToValidateBoxeaty' WHERE status.code = 'ORDER_TO_VALIDATE'");
    }

    public function down(Schema $schema): void
    {
    }
}
