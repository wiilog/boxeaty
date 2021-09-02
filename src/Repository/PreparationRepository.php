<?php

namespace App\Repository;

use App\Entity\Depository;
use App\Entity\Preparation;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends EntityRepository {

    public function getByDepository(?Depository $depository, User $currentOperator) {
        $qb = $this->createQueryBuilder('preparation')
            ->select('preparation.id AS id')
            ->addSelect('join_client.name AS client')
            ->addSelect('join_order.cratesAmount AS crateAmount')
            ->addSelect('join_order.tokensAmount AS tokenAmount')
            ->addSelect('join_order.number AS orderNumber')
            ->addSelect('join_operator.username AS operator')
            ->leftJoin('preparation.order', 'join_order')
            ->leftJoin('join_order.client', 'join_client')
            ->leftJoin('preparation.status', 'join_status')
            ->leftJoin('join_client.clientOrderInformation', 'join_clientOrderInformation')
            ->leftJoin('preparation.operator', 'join_operator')
            ->andWhere("join_status.code IN (:status)")
            ->setParameter("status", [Status::CODE_PREPARATION_TO_PREPARE, Status::CODE_PREPARATION_PREPARING]);

        if ($depository) {
            $qb
                ->andWhere("preparation.depository = :depository")
                ->setParameter("depository", $depository);
        }

        $res = $qb
            ->getQuery()
            ->execute();
        return Stream::from($res)
            ->map(fn(array $preparation) => array_merge(
                $preparation,
                [
                    'editable' => !isset($preparation['operator'])
                        || $preparation['operator'] === $currentOperator->getUsername(),
                ]
            ))
            ->toArray();
    }

}
