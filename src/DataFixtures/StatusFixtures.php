<?php

namespace App\DataFixtures;

use App\Entity\GlobalSetting;
use App\Entity\Status;
use App\Service\ExportService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class StatusFixtures extends Fixture implements FixtureGroupInterface {

    private const STATUSES = [
        Status::ORDER_TO_VALIDATE => "À valider",
        Status::ORDER_PLANNED => "Planifiée",
        Status::ORDER_TRANSIT => "En transit",
        Status::ORDER_FINISHED => "Terminée",
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $statusRepository = $manager->getRepository(Status::class);

        foreach (self::STATUSES as $code => $name) {
            if ($statusRepository->findOneBy(["name" => $name]) === null) {
                $status = (new Status())
                    ->setCode($code)
                    ->setName($name);

                $output->writeln("Created status \"{$status->getName()}\"");
                $manager->persist($status);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
