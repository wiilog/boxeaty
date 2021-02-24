<?php

namespace App\Repository;

use App\Entity\GlobalSetting;
use App\Helper\Stream;
use Doctrine\ORM\EntityRepository;

/**
 * @method GlobalSetting|null find($id, $lockMode = null, $lockVersion = null)
 * @method GlobalSetting|null findOneBy(array $criteria, array $orderBy = null)
 * @method GlobalSetting[]    findAll()
 * @method GlobalSetting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GlobalSettingRepository extends EntityRepository {

    public function getAll(): array {
        return Stream::from($this->findAll())
            ->keymap(fn($setting) => [$setting->getName(), $setting])
            ->toArray();
    }

    public function getCorrespondingCode(string $code): array {
        $setting = GlobalSetting::SETTING_CODE;
        $emptyKiosk = GlobalSetting::EMPTY_KIOSK_CODE;

        return $this->createQueryBuilder("setting")
            ->select("setting.name")
            ->where("setting.name = '$setting' OR setting.name = '$emptyKiosk'")
            ->andWhere("setting.value = :code")
            ->setParameter("code", $code)
            ->getQuery()
            ->getSingleResult();
    }

}
