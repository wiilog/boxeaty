<?php

namespace App\DataFixtures;

use App\Entity\OrderType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class OrderFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $orderTypeRepository = $manager->getRepository(OrderType::class);

        foreach (OrderType::LABELS as $code => $name) {
            if ($orderTypeRepository->findOneBy(["code" => $code]) === null) {
                $type = (new OrderType())
                    ->setCode($code)
                    ->setName($name);

                $output->writeln("Created type \"{$type->getName()}\"");
                $manager->persist($type);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
