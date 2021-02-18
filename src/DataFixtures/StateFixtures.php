<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\State;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class StateFixtures extends Fixture implements FixtureGroupInterface {

    private const STATES = [
        State::AVAILABLE,
        State::UNAVAILABLE,
        State::CLIENT,
        State::CONSUMER,
        State::OUT,
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $stateRepository = $manager->getRepository(State::class);

        foreach(self::STATES as $name) {
            if($stateRepository->findOneBy(["name" => $name]) === null) {
                $state = (new State())->setName($name);

                $output->writeln("Created state \"{$state->getName()}\"");
                $manager->persist($state);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
