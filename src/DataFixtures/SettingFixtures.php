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
        GlobalSetting::SETTING_CODE => "1532",
        GlobalSetting::EMPTY_KIOSK_CODE => "4578",
        GlobalSetting::BOX_CAPACITIES => null,
        GlobalSetting::BOX_SHAPES => null,
        GlobalSetting::PAYMENT_MODES => null,
        GlobalSetting::MAILER_HOST => null,
        GlobalSetting::MAILER_PORT => null,
        GlobalSetting::MAILER_USER => null,
        GlobalSetting::MAILER_PASSWORD => null,
        GlobalSetting::MAILER_SENDER_EMAIL => null,
        GlobalSetting::MAILER_SENDER_NAME => null,
    ];

    private const DELETED_SETTINGS = [
        "TABLET_PHRASE",
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $settingRepository = $manager->getRepository(GlobalSetting::class);

        foreach (self::DEFAULTS as $name => $default) {
            if ($settingRepository->findOneBy(["name" => $name]) === null) {
                $setting = (new GlobalSetting())
                    ->setName($name)
                    ->setValue($default);

                $output->writeln("Created setting \"{$setting->getName()}\"");
                $manager->persist($setting);
            }
        }

        $deleted = $settingRepository->findBy(["name" => self::DELETED_SETTINGS]);
        foreach($deleted as $setting) {
            $output->writeln("Deleted setting \"{$setting->getName()}\"");
            $manager->remove($setting);
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
