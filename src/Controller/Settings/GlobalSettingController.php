<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\GlobalSetting;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GlobalSettingController extends AbstractController {

    /**
     * @Route("/parametrage/global", name="settings")
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function settings(EntityManagerInterface $manager): Response {
        $settings = $manager->getRepository(GlobalSetting::class)->getAll();

        return $this->render("settings/global_settings/index.html.twig", [
            "csv_encoding" => $settings[GlobalSetting::CSV_EXPORTS_ENCODING],
            "setting_code" => $settings[GlobalSetting::SETTING_CODE],
            "empty_kiosk_code" => $settings[GlobalSetting::EMPTY_KIOSK_CODE],
            "box_capacities" => explode(",", $settings[GlobalSetting::BOX_CAPACITIES]->getValue()) ?: [],
            "box_shapes" => explode(",", $settings[GlobalSetting::BOX_SHAPES]->getValue()) ?: [],
            "validAuto" => $settings[GlobalSetting::VALIDATION_AUTO_DELAY],
            "qteBoxInf" => $settings[GlobalSetting::QUANTITY_BOX_INF],
            "mailer" => [
                "host" => $settings[GlobalSetting::MAILER_HOST],
                "port" => $settings[GlobalSetting::MAILER_PORT],
                "user" => $settings[GlobalSetting::MAILER_USER],
                "password" => $settings[GlobalSetting::MAILER_PASSWORD],
                "sender_email" => $settings[GlobalSetting::MAILER_SENDER_EMAIL],
                "sender_name" => $settings[GlobalSetting::MAILER_SENDER_NAME],
            ],
        ]);
    }

    /**
     * @Route("/parametrage/global/update", name="settings_update", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function update(Request $request, EntityManagerInterface $manager): Response {
        $content = $request->request->all();

        $settings = $manager->getRepository(GlobalSetting::class)->getAll();
        foreach ($settings as $setting) {
            $setting->setValue($content[$setting->getName()] ?? null ?: null);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Les paramétrages globaux ont été enregistrés avec succès",
        ]);
    }

}
