<?php

namespace App\DataFixtures;

use App\Entity\Location;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class LocationFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager) {
//        $output = new ConsoleOutput();
//
//        $locationRepository = $manager->getRepository(Location::class);
//
//        if(!$locationRepository->findOneBy(["code" => Location::DELIVERER])) {
//            $deliverer = (new Location())
//                ->setCode(Location::DELIVERER)
//                ->setName("Livreur")
//                ->setActive(true)
//                ->setClient(null)
//                ->setKiosk(false)
//                ->setDeposits(0)
//                ->setDescription("Emplacement des Box lorsqu'une borne vient d'être vidée");
//
//            $output->writeln("Created location \"Livreur\"");
//
//            $manager->persist($deliverer);
//            $manager->flush();
//        }
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
