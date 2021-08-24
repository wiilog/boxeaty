<?php

namespace App\Repository;

use App\Entity\Collect;
use App\Entity\Status;
use Doctrine\ORM\EntityRepository;

/**
 * @method Collect|null find($id, $lockMode = null, $lockVersion = null)
 * @method Collect|null findOneBy(array $criteria, array $orderBy = null)
 * @method Collect[]    findAll()
 * @method Collect[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectRepository extends EntityRepository {

    public function getTotalQuantityByClientAndCollectedDate($client, $dateMin, $dateMax)
    {
        $qb = $this->createQueryBuilder('collecte')
            ->select('sum(lines.quantity)')
            ->andWhere('collecte.collectedAt BETWEEN :dateMin AND :dateMax')
            ->andWhere('clientOrder.client =:client')
            ->join('collecte.order', 'clientOrder')
            ->join('clientOrder.lines', 'lines')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax,
                'client' => $client,
            ]);
        $result = $qb
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? intval($result) : 0;
    }

    public function getPendingCollects() {

        $qb = $this->createQueryBuilder('collect')
            ->select('collect.id AS id')
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
            ->leftJoin('join_client.depository', 'join_depository')
            ->leftJoin('collect.status', 'join_status')
            ->leftJoin('collect.crates', 'join_crates')
            ->leftJoin('collect.pickLocation', 'join_pickLocation')
            ->where('join_status.code = :status')
            ->groupBy('id')
            ->setParameter('status', Status::CODE_COLLECT_TRANSIT);

        return $qb
            ->getQuery()
            ->getArrayResult();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('collect')
            ->select('collect.number')
            ->where('collect.number LIKE :value')
            ->orderBy('collect.createdAt', 'DESC')
            ->addOrderBy('collect.number', 'DESC')
            ->setParameter('value', Collect::PREFIX_NUMBER . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

}
