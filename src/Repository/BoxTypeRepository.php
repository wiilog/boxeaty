<?php

namespace App\Repository;

use App\Entity\BoxType;
use App\Helper\FormatHelper;
use App\Helper\QueryHelper;
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
            $qb->where($qb->expr()->orX(
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
            ->where("box_type.name LIKE :search")
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
            ->where("box_type.name LIKE :kit")
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

}
