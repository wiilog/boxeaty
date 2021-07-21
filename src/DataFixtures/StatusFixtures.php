<?php

namespace App\DataFixtures;

use App\Entity\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class StatusFixtures extends Fixture implements FixtureGroupInterface {

    private const STATUSES = [
        Status::ORDER => [
            Status::ORDER_TO_VALIDATE => "À valider",
            Status::ORDER_PLANNED => "Planifiée",
            Status::ORDER_TRANSIT => "En transit",
            Status::ORDER_FINISHED => "Terminée",
        ],
        Status::ROUND => [
            Status::ROUND_CREATED => "Créé",
            Status::ROUND_AWAITING_DELIVERER => "Attente livreur",
            Status::ROUND_TAKEN_DELIVERER => "Enlevée par livreur",
            Status::ROUND_FINISHED => "Terminé",
        ],
        Status::PREPARATION => [
            Status::PREPARATION_PREPARING => "En préparation",
            Status::PREPARATION_PREPARED => "Préparé",
        ],
        Status::DELIVERY => [
            Status::DELIVERY_PLANNED => "Planifiée",
            Status::DELIVERY_PREPARING => "En préparation",
            Status::DELIVERY_AWAITING_DELIVERER => "Attente livreur",
            Status::DELIVERY_TRANSIT => "En transit",
            Status::DELIVERY_DELIVERED => "Livrée",
        ],
        Status::COLLECT => [
            Status::COLLECT_TRANSIT => "En transit",
            Status::COLLECT_FINISHED => "Finished",
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
