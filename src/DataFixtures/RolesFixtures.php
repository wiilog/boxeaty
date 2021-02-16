<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class RolesFixtures extends Fixture implements FixtureGroupInterface {

    private const ROLES = [
        Role::ROLE_NO_ACCESS => "Aucun accès",
        Role::ROLE_ADMIN => "Administrateur",
    ];

    private const PERMISSIONS = [
        Role::ROLE_NO_ACCESS => [],
        Role::ROLE_ADMIN => [
            Role::MANAGE_USERS,
            Role::MANAGE_ROLES,
            Role::MANAGE_GROUPS,
            Role::MANAGE_CLIENTS,
        ],
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $roleRepository = $manager->getRepository(Role::class);

        foreach(self::ROLES as $code => $label) {
            if($roleRepository->findOneBy(["code" => $code]) === null) {
                $role = (new Role())
                    ->setCode($code)
                    ->setName($label)
                    ->setPermissions(self::PERMISSIONS[$code])
                    ->setActive(true);

                $output->writeln("Created role \"{$role->getName()}\"");
                $manager->persist($role);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
