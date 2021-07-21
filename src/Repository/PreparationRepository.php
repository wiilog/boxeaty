<?php

namespace App\Repository;

use App\Entity\Depository;
use App\Entity\Preparation;
use App\Entity\Status;
use Doctrine\ORM\EntityRepository;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends EntityRepository {

    public function getByDepository(?Depository $depository)
    {
        $qb = $this->createQueryBuilder('preparation')
            ->select('preparation.id AS id')
            ->addSelect('join_client.name AS client')
            ->addSelect('join_order.cratesAmount AS crate_amount')
            ->addSelect('join_clientOrderInformation.tokenAmount AS token_amount')
            ->addSelect('join_order.number AS order_number')
            ->leftJoin('preparation.order', 'join_order')
            ->leftJoin('join_order.client', 'join_client')
            ->leftJoin('preparation.status', 'join_status')
            ->leftJoin('join_client.clientOrderInformation', 'join_clientOrderInformation')
            ->andWhere("join_status.code = :status")
            ->setParameter("status", Status::PREPARATION_PREPARING);

        if($depository) {
            $qb
                ->andWhere("preparation.depository = :depository")
                ->setParameter("depository", $depository);
        }

        return $qb
            ->getQuery()
            ->execute();
    }
}
