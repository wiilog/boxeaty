<?php

namespace App\DataFixtures;

use App\Entity\GlobalSetting;
use App\Service\ExportService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class SettingFixtures extends Fixture implements FixtureGroupInterface {

    private const DEFAULTS = [
        GlobalSetting::CSV_EXPORTS_ENCODING => ExportService::ENCODING_UTF8,
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $settingRepository = $manager->getRepository(GlobalSetting::class);

        foreach (self::DEFAULTS as $name => $default) {
            if ($settingRepository->findOneBy(["name" => $name]) === null) {
                $setting = (new GlobalSetting())
                    ->setName($name)
                    ->setValue($default);;

                $output->writeln("Created setting \"{$setting->getName()}\"");
                $manager->persist($setting);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
