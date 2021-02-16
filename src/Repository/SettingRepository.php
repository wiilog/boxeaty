<?php

namespace App\Repository;

use App\Entity\Setting;
use App\Helper\Stream;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Setting|null find($id, $lockMode = null, $lockVersion = null)
 * @method Setting|null findOneBy(array $criteria, array $orderBy = null)
 * @method Setting[]    findAll()
 * @method Setting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SettingRepository extends EntityRepository {

    public function getAll(): array {
        return Stream::from($this->findAll())
            ->keymap(fn($setting) => [$setting->getName(), $setting])
            ->toArray();
    }

}
