<?php

namespace App\Repository;

use App\Entity\Collect;
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
            ->where('collecte.collectedAt BETWEEN :dateMin AND :dateMax')
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

}
