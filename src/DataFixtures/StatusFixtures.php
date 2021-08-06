<?php

namespace App\DataFixtures;

use App\Entity\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class StatusFixtures extends Fixture implements FixtureGroupInterface {

    private const STATUSES = [
        Status::CATEGORY_ORDER => [
            Status::CODE_ORDER_TO_VALIDATE => "À valider",
            Status::CODE_ORDER_PLANNED => "Planifiée",
            Status::CODE_ORDER_TRANSIT => "En transit",
            Status::CODE_ORDER_FINISHED => "Terminée",
        ],
        Status::CATEGORY_ROUND => [
            Status::CODE_ROUND_CREATED => "Créé",
            Status::CODE_ROUND_AWAITING_DELIVERER => "Attente livreur",
            Status::CODE_ROUND_TAKEN_DELIVERER => "Enlevée par livreur",
            Status::CODE_ROUND_FINISHED => "Terminé",
        ],
        Status::CATEGORY_PREPARATION => [
            Status::CODE_PREPARATION_TO_PREPARE => "A préparer",
            Status::CODE_PREPARATION_PREPARING => "En préparation",
            Status::CODE_PREPARATION_PREPARED => "Préparé",
        ],
        Status::CATEGORY_DELIVERY => [
            Status::CODE_DELIVERY_PLANNED => "Planifiée",
            Status::CODE_DELIVERY_PREPARING => "En préparation",
            Status::CODE_DELIVERY_AWAITING_DELIVERER => "Attente livreur",
            Status::CODE_DELIVERY_TRANSIT => "En transit",
            Status::CODE_DELIVERY_DELIVERED => "Livrée",
        ],
        Status::CATEGORY_COLLECT => [
            Status::CODE_COLLECT_TRANSIT => "En transit",
            Status::CODE_COLLECT_FINISHED => "Finished",
        ],
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $statusRepository = $manager->getRepository(Status::class);

        foreach (self::STATUSES as $category => $statuses) {
            foreach ($statuses as $code => $name) {
                $status = $statusRepository->findOneBy(["code" => $code]);
                if ($status === null) {
                    $status = new Status();

                    $output->writeln("Created status \"$name\"");
                    $manager->persist($status);
                }

                $status->setCategory($category)
                    ->setCode($code)
                    ->setName($name);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
