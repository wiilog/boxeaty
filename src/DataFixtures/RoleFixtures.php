<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class RoleFixtures extends Fixture implements FixtureGroupInterface {

    private const ROLES = [
        Role::ROLE_NO_ACCESS => [
            "name" => "Aucun accès",
            "emailsNewAccounts" => false,
        ],
        Role::ROLE_ADMIN => [
            "name" => "Super Administrateur",
            "emailsNewAccounts" => true,
        ],
    ];

    private const PERMISSIONS = [
        Role::ROLE_NO_ACCESS => [],
        Role::ROLE_ADMIN => [
            Role::MANAGE_SETTINGS,
            Role::MANAGE_USERS,
            Role::MANAGE_ROLES,
            Role::MANAGE_QUALITIES,
            Role::MANAGE_IMPORTS,
            Role::MANAGE_EXPORTS,
            Role::MANAGE_GROUPS,
            Role::MANAGE_CLIENTS,
            Role::MANAGE_LOCATIONS,
            Role::MANAGE_MOVEMENTS,
            Role::MANAGE_DEPOSIT_TICKETS,
            Role::MANAGE_BOX_TYPES,
            Role::MANAGE_BOXES,
            Role::MANAGE_COUNTER_ORDERS,
            Role::MANAGE_DEPOSITORIES,
            Role::MANAGE_COUNTER_ORDERS,
            Role::VIEW_ALL_ORDERS,
            Role::CREATE_CLIENT_ORDERS,
            Role::MANAGE_CLIENT_ORDERS,
            Role::VIEW_INDICATORS,
            Role::MANAGE_PLANNING,
            Role::TREAT_PREPARATIONS,
            Role::TREAT_DELIVERIES,
            Role::TREAT_RECEPTIONS,
            Role::TREAT_COLLECTS,
        ],
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $roleRepository = $manager->getRepository(Role::class);

        foreach (self::ROLES as $code => $config) {
            if ($roleRepository->findOneBy(["code" => $code]) === null) {
                $role = (new Role())
                    ->setCode($code)
                    ->setName($config["name"])
                    ->setPermissions(self::PERMISSIONS[$code])
                    ->setAllowEditOwnGroupOnly(false)
                    ->setReceiveMailsNewAccounts($config["emailsNewAccounts"])
                    ->setShowNewOrderOnHome(false)
                    ->setShowIndicatorsOnHome(false)
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
