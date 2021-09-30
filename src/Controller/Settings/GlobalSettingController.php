<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\BoxType;
use App\Entity\DeliveryMethod;
use App\Entity\GlobalSetting;
use App\Entity\Role;
use App\Entity\WorkFreeDay;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\WorkFreeDayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/global")
 */
class GlobalSettingController extends AbstractController {

    /**
     * @Route("/", name="settings")
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function settings(Request                $request,
                             EntityManagerInterface $entityManager): Response {
        $settings = $entityManager->getRepository(GlobalSetting::class)->getAll();
        $boxTypeRepository = $entityManager->getRepository(BoxType::class);
        $crateTypeId = $settings[GlobalSetting::DEFAULT_CRATE_TYPE]->getValue();

        return $this->render("settings/global_settings/index.html.twig", [
            "initial_work_free_days" => $this->workFreeDaysApi($request, $entityManager)->getContent(),
            "work_free_days_order" => WorkFreeDayRepository::DEFAULT_DATATABLE_ORDER,
            "csv_encoding" => $settings[GlobalSetting::CSV_EXPORTS_ENCODING],
            "setting_code" => $settings[GlobalSetting::SETTING_CODE],
            "empty_kiosk_code" => $settings[GlobalSetting::EMPTY_KIOSK_CODE],
            "box_capacities" => $this->asArray($settings, GlobalSetting::BOX_CAPACITIES),
            "box_shapes" => $this->asArray($settings, GlobalSetting::BOX_SHAPES),
            "payment_modes" => $this->asArray($settings, GlobalSetting::PAYMENT_MODES),
            "icons" => DeliveryMethod::TRANSPORT_TYPES,
            "initial_delivery_method" => $this->transportModeApi($request, $entityManager)->getContent(),
            "delivery_method_order" => DeliveryMethod::DEFAULT_DATATABLE_ORDER,
            "auto_validation_delay" => $settings[GlobalSetting::AUTO_VALIDATION_DELAY],
            "auto_validation_box_quantity" => $settings[GlobalSetting::AUTO_VALIDATION_BOX_QUANTITY],
            "mailer" => [
                "host" => $settings[GlobalSetting::MAILER_HOST],
                "port" => $settings[GlobalSetting::MAILER_PORT],
                "user" => $settings[GlobalSetting::MAILER_USER],
                "password" => $settings[GlobalSetting::MAILER_PASSWORD],
                "sender_email" => $settings[GlobalSetting::MAILER_SENDER_EMAIL],
                "sender_name" => $settings[GlobalSetting::MAILER_SENDER_NAME],
            ],
            'default_crate_type' => !empty($crateTypeId)
                ? $boxTypeRepository->find($crateTypeId)
                : null,
        ]);
    }

    /**
     * @Route("/update", name="settings_update", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function update(Request $request, EntityManagerInterface $manager): Response {
        $content = $request->request->all();

        $settings = $manager->getRepository(GlobalSetting::class)->getAll();
        foreach($settings as $setting) {
            $setting->setValue($content[$setting->getName()] ?? null);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Les paramétrages globaux ont été enregistrés avec succès",
        ]);
    }

    /**
     * @Route("/jours-feries/api", name="work_free_day_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function workFreeDaysApi(Request $request, EntityManagerInterface $manager): Response {
        $days = $manager->getRepository(WorkFreeDay::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];
        /** @var WorkFreeDay $day */
        foreach($days["data"] as $day) {
            $data[] = [
                "id" => $day->getId(),
                "day" => $day->getDay() . " " . FormatHelper::MONTHS[$day->getMonth()],
                "actions" => '<button class="silent w-100" data-listener="delete"><i class="icon bxi bxi-trash"></i></button>',
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $days["total"],
            "recordsFiltered" => $days["filtered"],
        ]);
    }

    /**
     * @Route("/jours-feries/ajouter", name="work_free_day_add", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function addWorkFreeDay(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(WorkFreeDay::class)->findOneBy([
            "day" => $content->day,
            "month" => $content->month,
        ]);

        // TODO Gérer la validation les derniers jours du mois

        if($existing) {
            $form->addError("day", "Ce jour ferié existe déjà");
            $form->addError("month", "Ce jour ferié existe déjà");
        }

        if($form->isValid()) {
            $day = new WorkFreeDay();
            $day->setDay($content->day)
                ->setMonth($content->month);

            $manager->persist($day);
            $manager->flush();
        } else {
            return $form->errors();
        }

        return $this->json([
            "success" => true,
            "message" => "Jour férié ou non-ouvré ajouté",
        ]);
    }

    /**
     * @Route("/jours-feries/supprimer/{day}", name="work_free_day_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function deleteWorkFreeDay(EntityManagerInterface $manager, WorkFreeDay $day): Response {
        $manager->remove($day);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Jour férié ou non-ouvré supprimé",
        ]);
    }

    private function asArray(array $settings, string $key): array {
        $values = $settings[$key]->getValue();
        if($values) {
            return explode(",", $values);
        } else {
            return [];
        }
    }

    /**
     * @Route("/mode-transport/api", name="delivery_method_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function transportModeApi(Request $request, EntityManagerInterface $manager): Response {
        $transportModes["data"] = $manager->getRepository(DeliveryMethod::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];

        /** @var DeliveryMethod $transportMode */
        foreach($transportModes["data"]["data"] as $transportMode) {
            $icon = $transportMode->getIcon();
            $data[] = [
                "id" => $transportMode->getId(),
                "name" => $transportMode->getName(),
                "icon" => "<i style='height: 20px' class='icon ico bxi bxi-${icon}'></i>",
                "actions" => '<button class="silent w-100" data-listener="delete"><i class="icon bxi bxi-trash"></i></button>',
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $transportModes["data"]["total"],
            "recordsFiltered" => $transportModes["data"]["filtered"],
        ]);
    }

    /**
     * @Route("/methode-livraison/ajouter", name="delivery_mode_add", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function addDeliveryMethod(Request $request, EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();

        $name = $content->nameDeliveryMethode;
        $icon = $content->icon;

        $existing = $manager->getRepository(DeliveryMethod::class)->findOneBy([
            "name" => $name,
        ]);

        if($existing && $existing->getDeleted() && $existing->getIcon() === $icon) {
            $existing->setDeleted(false);
            $response = [
                "success" => true,
                "message" => "Le type de mobilité ${name} a bien été réactivé",
            ];
        } else if($existing && !$existing->getDeleted()) {
            $response = [
                "success" => false,
                "message" => "Ce type de mobilité existe déjà",
            ];
        } else {
            $deliveryMethod = (new DeliveryMethod())
                ->setName($name)
                ->setType(array_search($icon, DeliveryMethod::TRANSPORT_TYPES))
                ->setIcon($icon);

            $manager->persist($deliveryMethod);
            $response = [
                "success" => true,
                "message" => "Le type de mobilité ${name} a bien été créé",
            ];
        }
        $manager->flush();

        return $this->json($response);
    }

    /**
     * @Route("/delivery-method/supprimer/{deliveryMethod}", name="delivery_methode_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_SETTINGS)
     */
    public function deleteDeliveryMethod(EntityManagerInterface $manager, DeliveryMethod $deliveryMethod): Response {
        $deliveryMethod->setDeleted(true);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Type de mobilité supprimé",
        ]);
    }

}
