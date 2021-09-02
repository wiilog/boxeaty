<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class ClientFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $clientRepository = $manager->getRepository(Client::class);
        $client = $clientRepository->findOneBy(["name" => Client::BOXEATY]);

        if(!$client) {
            $group = $manager->getRepository(Group::class)->findOneBy(["name" => Client::BOXEATY]);
            if(!$group) {
                $group = (new Group())
                    ->setName(Client::BOXEATY)
                    ->setActive(true);

                $manager->persist($group);
            }

            $client = (new Client())
                ->setGroup($group)
                ->setName(Client::BOXEATY)
                ->setAddress("13 rue du 8 mai 1945, 33150 Cenon")
                ->setLatitude(0.0)
                ->setLongitude(0.0)
                ->setPhoneNumber("0648322482")
                ->setDepositTicketValidity(4)
                ->setActive(true)
                ->setIsMultiSite(true);

            $client->getClientOrderInformation()
                ->setComment("Créé automatiquement");

            $output->writeln("Created client BoxEaty");

            $manager->persist($client);
        } else {
            $client->setActive(true);
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
