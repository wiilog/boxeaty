<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\ClientOrder;
use App\Entity\Depository;
use App\Entity\Status;
use App\Helper\FormatHelper;
use App\Helper\QueryHelper;
use App\Service\BoxStateService;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method BoxType|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoxType|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoxType[]    findAll()
 * @method BoxType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxTypeRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("box_type")
            ->select("box_type.name AS name")
            ->addSelect("box_type.price AS price")
            ->addSelect("box_type.active AS active")
            ->addSelect("box_type.capacity AS capacity")
            ->addSelect("box_type.shape AS shape")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("box_type");
        $total = QueryHelper::count($qb, "box_type");

        if ($search) {
            $qb->andWhere($qb->expr()->orX(
                "box_type.name LIKE :search",
                "box_type.price LIKE :search",
                "box_type.capacity LIKE :search",
                "box_type.shape LIKE :search",
            ))->setParameter("search", "%$search%");
        }


        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("box_type.$column", $order["dir"]);
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("box_type.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "box_type");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search, $extended = false) {
        $boxTypes = $this->createQueryBuilder("box_type")
            ->andWhere("box_type.name LIKE :search")
            ->andWhere("box_type.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getResult();

        return Stream::from($boxTypes)
            ->map(fn (BoxType $boxType) => [
                'id' => $boxType->getId(),
                'text' => $extended
                    ? $boxType->getName() . ' - ' . ($boxType->getVolume()
                        ? $boxType->getVolume() . 'm³'
                        : 'N/C') . ' - ' . FormatHelper::price($boxType->getPrice())
                    : $boxType->getName(),
                'name' => $boxType->getName(),
                'price' => $boxType->getPrice(),
                'volume' => $boxType->getVolume(),
                'image' => $boxType->getImage()
                    ? $boxType->getImage()->getPath()
                    : null,
            ])
            ->values();
    }
    public function findStarterKit() {
        $boxType = $this->createQueryBuilder("box_type")
            ->andWhere("box_type.name LIKE :kit")
            ->setParameter("kit", BoxType::STARTER_KIT)
            ->getQuery()
            ->getSingleResult();

        $volumeLabel = (
            $boxType->getVolume()
                ? $boxType->getVolume() . 'm³'
                : 'N/C'
        );

        return [
            'id' => $boxType->getId(),
            'text' => $boxType->getName() . ' - ' . $volumeLabel . ' - ' . FormatHelper::price($boxType->getPrice()),
            'name' => $boxType->getName(),
            'price' => $boxType->getPrice(),
            'volume' => $boxType->getVolume(),
            'image' => $boxType->getImage()
                ? $boxType->getImage()->getPath()
                : null,
        ];
    }

    public function countAvailableInDepository(Depository $depository, array $types = []): array {
        $totalAvailableResult = $this->createQueryBuilder("box_type")
            ->select("box_type.id AS id")
            ->addSelect("COUNT(box.id) AS count")
            ->addSelect("owner.id AS client")
            ->innerJoin("box_type.boxes", "box")
            ->innerJoin("box.owner", "owner")
            ->innerJoin("box.location", "location")
            ->innerJoin("box.quality", "quality")
            ->innerJoin("location.depository", "depository")
            ->andWhere("box.state = :availableState")
            ->andWhere("depository = :depository")
            ->andWhere("quality.clean = 1")
            ->andWhere("box_type IN (:types)")
            ->setParameter("types", $types)
            ->setParameter("availableState", BoxStateService::STATE_BOX_AVAILABLE)
            ->setParameter("depository", $depository)
            ->groupBy("box_type.id, owner.id")
            ->getQuery()
            ->getResult();

        $totalAvailable = [];
        foreach($totalAvailableResult as $line) {
            $totalAvailable[$line["id"]][$line["client"] ?: "any"] = $line["count"];
        }

        $inUnpreparedResult = $this->createQueryBuilder("box_type")
            ->select("box_type.id AS id")
            ->addSelect("SUM(order_lines.quantity) AS count")
            ->addSelect("order_client.id AS client")
            ->join("box_type.clientOrderLines", "order_lines")
            ->join("order_lines.clientOrder", "order")
            ->join("order.client", "order_client")
            ->join("order.status", "status")
            ->andWhere("status.name = :status")
            ->andWhere("box_type IN (:types)")
            ->setParameter("types", $types)
            ->setParameter("status", Status::CODE_ORDER_PLANNED)
            ->groupBy("box_type.id, order_client.id")
            ->getQuery()
            ->getResult();

        $inUnprepared = [];
        foreach($inUnpreparedResult as $line) {
            $owner = $line["client"] ?: Box::OWNER_BOXEATY;
            $inUnprepared[$line["id"]][$owner] = $line["count"];
        }

        foreach($totalAvailable as $type => $clients) {
            foreach($clients as $client => $count) {
                dump($inUnprepared[$type][$client] ?? 0);
                $totalAvailable[$type][$client] = $count - ($inUnprepared[$type][$client] ?? 0);
            }
        }

        return $totalAvailable;
    }

}
