<?php

namespace App\Repository;

use App\Entity\GlobalSetting;
use App\Helper\Stream;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

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

    public function getValue(string $key): ?string {
        try {
            return $this->createQueryBuilder("setting")
                ->select("setting.value")
                ->where("setting.name = '$key'")
                ->getQuery()
                ->getSingleResult()["value"];
        } catch (NoResultException $ignored) {
            return null;
        }
    }

    public function getCorrespondingCode(string $code): ?string {
        $setting = GlobalSetting::SETTING_CODE;
        $emptyKiosk = GlobalSetting::EMPTY_KIOSK_CODE;

        try {
            return $this->createQueryBuilder("setting")
                ->select("setting.name")
                ->where("setting.name = '$setting' OR setting.name = '$emptyKiosk'")
                ->andWhere("setting.value = :code")
                ->setParameter("code", $code)
                ->getQuery()
                ->getSingleResult()["name"];
        } catch (NoResultException $ignored) {
            return null;
        }
    }

    public function getMailer(): array {
        $configs = $this->createQueryBuilder("setting")
            ->select("setting.name, setting.value")
            ->where("setting.name LIKE 'MAILER_%'")
            ->getQuery()
            ->getArrayResult();

        return Stream::from($configs)
            ->keymap(fn($input) => [$input["name"], $input["value"]])
            ->toArray();
    }

}
