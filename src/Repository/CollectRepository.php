<?php

namespace App\Repository;

use App\Entity\Collect;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @method Collect|null find($id, $lockMode = null, $lockVersion = null)
 * @method Collect|null findOneBy(array $criteria, array $orderBy = null)
 * @method Collect[]    findAll()
 * @method Collect[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectRepository extends EntityRepository {

    public function getTotalQuantityByClientAndCollectedDate($client, $dateMax, $dateMin = null) {
        $qb = $this->createQueryBuilder('collecte')
            ->select('sum(lines.quantity)')
            ->andWhere('collecte.treatedAt BETWEEN :dateMin AND :dateMax')
            ->andWhere($dateMin
                ? 'collecte.treatedAt BETWEEN :dateMin AND :dateMax'
                : 'collecte.treatedAt <= :dateMax')
            ->andWhere('clientOrder.client =:client')
            ->join('collecte.clientOrder', 'clientOrder')
            ->join('clientOrder.lines', 'lines')
            ->setParameters([
                'dateMax' => $dateMax,
                'client' => $client,
            ]);

        if ($dateMin) {
            $qb->setParameter('dateMin', $dateMin);
        }

        $result = $qb
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? intval($result) : 0;
    }

    public function getPendingCollects(?User $user) {
        $qb = $this->createQueryBuilder("collect");

        if(!$user->hasRight(Role::TREAT_ALL_COLLECTS)) {
            $qb->andWhere("collect.operator = :operator")
                ->setParameter("operator", $user);
        }

        return $qb->select('collect.id AS id')
            ->addSelect('collect.number AS number')
            ->addSelect('collect.tokens AS token_amount')
            ->addSelect('join_depository.name AS depository')
            ->addSelect('join_client.name AS client')
            ->addSelect('join_client.address AS address')
            ->addSelect('join_user.username AS main_contact')
            ->addSelect('join_client.phoneNumber AS phone_number')
            ->addSelect('COUNT(join_crates.id) AS crate_amount')
            ->addSelect('join_pickLocation.name AS pick_location')
            ->leftJoin('collect.client', 'join_client')
            ->leftJoin('join_client.contact', 'join_user')
            ->leftJoin('join_client.clientOrderInformation', 'join_client_order_information')
            ->leftJoin('join_client_order_information.depository', 'join_depository')
            ->leftJoin('collect.status', 'join_status')
            ->leftJoin('collect.crates', 'join_crates')
            ->leftJoin('collect.pickLocation', 'join_pickLocation')
            ->andWhere('join_status.code = :status')
            ->groupBy('id')
            ->setParameter('status', Status::CODE_COLLECT_TRANSIT)
            ->getQuery()
            ->getArrayResult();
    }

    public function getLastNumberByDate(string $prefix, string $date): ?string {
        $result = $this->createQueryBuilder('collect')
            ->select('collect.number')
            ->andWhere('collect.number LIKE :value')
            ->orderBy('collect.createdAt', 'DESC')
            ->addOrderBy('collect.number', 'DESC')
            ->setMaxResults(1)
            ->setParameter('value', "$prefix$date%")
            ->getQuery()
            ->execute();

        return $result ? $result[0]['number'] : null;
    }

}
