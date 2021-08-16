<?php

namespace App\DataFixtures;

use App\Entity\OrderType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class OrderFixtures extends Fixture implements FixtureGroupInterface {

    private const TYPES = [
        OrderType::PURCHASE_TRADE => "Achat/négoce",
        OrderType::AUTONOMOUS_MANAGEMENT => "Gestion autonome",
        OrderType::ONE_TIME_SERVICE => "Prestation ponctuelle",
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $orderTypeRepository = $manager->getRepository(OrderType::class);

        foreach (self::TYPES as $code => $name) {
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