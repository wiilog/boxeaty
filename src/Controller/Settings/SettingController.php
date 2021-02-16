<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Setting;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingController extends AbstractController {

    /**
     * @Route("/parametrage/global", name="settings")
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function settings(EntityManagerInterface $manager): Response {
        $settings = $manager->getRepository(Setting::class)->getAll();

        return $this->render("settings/setting/index.html.twig", [
            "csv_encoding" => $settings[Setting::CSV_EXPORTS_ENCODING],
        ]);
    }

    /**
     * @Route("/parametrage/global/update", name="settings_update", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function update(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent(), true);

        $settings = $manager->getRepository(Setting::class)->getAll();
        foreach ($content as $name => $value) {
            if (isset($settings[$name])) {
                $setting = $settings[$name];
            } else {
                $setting = (new Setting())->setName($name);
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
