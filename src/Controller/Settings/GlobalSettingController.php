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
        ]);
    }

    /**
     * @Route("/parametrage/global/update", name="settings_update", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function update(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent(), true);

        $settings = $manager->getRepository(GlobalSetting::class)->getAll();
        foreach ($content as $name => $value) {
            if (isset($settings[$name])) {
                $setting = $settings[$name];
            } else {
                $setting = (new GlobalSetting())->setName($name);
                $manager->persist($setting);
            }

            $setting->setValue($value);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Les paramétrages globaux ont été enregistrés avec succès",
        ]);
    }

}
