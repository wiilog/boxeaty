<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\Depository;
use App\Entity\Location;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\LocationRepository;
use App\Service\BoxStateService;
use App\Service\ExportService;
use App\Service\LocationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/referentiel/emplacements")
 */
class LocationController extends AbstractController
{

    /**
     * @Route("/liste", name="locations_list")
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response
    {
        $params = json_decode($request->getContent(), true);
        $filters = $params["filters"] ?? [];

        if (isset($filters["depository"])) {
            $depositoryId = $manager->find(Depository::class, $filters['depository'])->getId();
            $boxRepository = $manager->getRepository(Box::class);
            $stockLocationType = array_search(Location::STOCK, Location::LOCATION_TYPES);

            $crateUnavailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_UNAVAILABLE, 0, $depositoryId);
            $crateAvailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_AVAILABLE, 0, $depositoryId, $stockLocationType);
            $boxUnavailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_UNAVAILABLE, 1, $depositoryId);
            $boxAvailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_AVAILABLE, 1, $depositoryId, $stockLocationType);
        }

        return $this->render("referential/location/index.html.twig", [
            "new_location" => new Location(),
            "initial_locations" => $this->api($request, $manager)->getContent(),
            "locations_order" => LocationRepository::DEFAULT_DATATABLE_ORDER,
            "crateUnavailable" => $crateUnavailable ?? "--",
            "crateAvailable" => $crateAvailable ?? "--",
            "boxUnavailable" => $boxUnavailable ?? "--",
            "boxAvailable" => $boxAvailable ?? "--",
        ]);
    }

    /**
     * @Route("/api", name="locations_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $boxRepository = $manager->getRepository(Box::class);
        $locations = $manager->getRepository(Location::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $data = [];
        /** @var Location $location */
        foreach ($locations["data"] as $location) {
            $data[] = [
                "id" => $location->getId(),
                "kiosk" => $location->isKiosk() ? "Borne" : "Emplacement",
                "name" => $location->getName(),
                "depository" => $location->getDepository() ? $location->getDepository()->getName() : '-',
                "client_name" => FormatHelper::named($location->getClient()),
                "active" => FormatHelper::bool($location->isActive()),
                "client" => FormatHelper::named($location->getClient()),
                "description" => $location->getDescription() ?: "-",
                "capacity" => $location->getCapacity() ?? "-",
                "location_type" => $location->getType() ? Location::LOCATION_TYPES[$location->getType()] : '-',
                "container_amount" => $boxRepository->count(["location" => $location]),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                    "empty" => $location->isKiosk(),
                ]),
            ];
        }

        $params = json_decode($request->getContent(), true);
        $filters = $params['filters'] ?? [];
        $chartLabels = [];
        $dataCustomerState = [];
        $dataAvailableState = [];
        $dataUnavailableState = [];
        $dataOutState = [];

        if (!empty($filters) && count($filters) == 3) {
            $depositoryRepository = $manager->getRepository(Depository::class);
            $filters = $params['filters'];
            $depository = $depositoryRepository->find($filters['depository']);
            $locationsId = Stream::from($depository->getLocations())
                ->map(fn(Location $location) => $location->getId())
                ->toArray();
            $startDate = DateTime::createFromFormat("Y-m-d", $filters["from"]);
            $endDate = DateTime::createFromFormat("Y-m-d", $filters["to"]);
            $boxRecordRepository = $manager->getRepository(BoxRecord::class);

            for ($i = clone $startDate; $i <= $endDate; $i->modify("+1 day")) {
                $dateMin = clone $i;
                $dateMax = clone $i;
                $dateMin->setTime(0, 0, 0);
                $dateMax->setTime(23, 59, 59);
                $chartLabels[] = $i->format("d/m/Y");
                $dataCustomerState[] = $boxRecordRepository->getNumberBoxByStateAndDate($dateMin, $dateMax, BoxStateService::STATE_BOX_CONSUMER, $locationsId);
                $dataOutState[] = $boxRecordRepository->getNumberBoxByStateAndDate($dateMin, $dateMax, BoxStateService::STATE_BOX_OUT, $locationsId);
                $dataUnavailableState[] = $boxRecordRepository->getNumberBoxByStateAndDate($dateMin, $dateMax, BoxStateService::STATE_BOX_UNAVAILABLE, $locationsId);
                $dataAvailableState[] = $boxRecordRepository->getNumberBoxByStateAndDate($dateMin, $dateMax, BoxStateService::STATE_BOX_AVAILABLE, $locationsId);
            }
        }

        $config = [
            'type' => 'line',
            'data' => [
                'labels' => $chartLabels,
                'datasets' => [
                    [
                        'label' => "Consommateur",
                        'data' => $dataCustomerState,
                        'backgroundColor' => ['#1E1F44'],
                        'borderColor' => ['#1E1F44'],
                    ],
                    [
                        'label' => "Sorti",
                        'data' => $dataOutState,
                        'backgroundColor' => ['#EB611B'],
                        'borderColor' => ['#EB611B'],
                    ],
                    [
                        'label' => "Indisponible",
                        'data' => $dataUnavailableState,
                        'backgroundColor' => ['#890620'],
                        'borderColor' => ['#890620'],
                    ],
                    [
                        'label' => "Disponible",
                        'data' => $dataAvailableState,
                        'backgroundColor' => ['#76B39D'],
                        'borderColor' => ['#76B39D'],
                    ],
                ],
            ],
        ];
        $depositoryRepository = $manager->getRepository(Depository::class);
        $depository = isset($filters['depository']) ? $depositoryRepository->find($filters['depository']) : null;

        if ($depository) {
            $depositoryId = $depository->getId();
            $stockLocationType = array_search(Location::STOCK, Location::LOCATION_TYPES);
            $crateUnavailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_UNAVAILABLE, 0, $depositoryId);
            $crateAvailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_AVAILABLE, 0, $depositoryId, $stockLocationType);
            $boxUnavailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_UNAVAILABLE, 1, $depositoryId);
            $boxAvailable = $boxRepository->getLocationData(BoxStateService::STATE_BOX_AVAILABLE, 1, $depositoryId, $stockLocationType);
        }

        return $this->json([
            "config" => !empty($config) ? json_encode($config) : [],
            "data" => $data,
            "recordsTotal" => $locations["total"],
            "recordsFiltered" => $locations["filtered"],
            "crateUnavailable" => $crateUnavailable ?? '--',
            "crateAvailable" => $crateAvailable ?? '--',
            "boxUnavailable" => $boxUnavailable ?? '--',
            "boxAvailable" => $boxAvailable ?? '--',
        ]);
    }

    /**
     * @Route("/nouveau", name="location_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function new(Request $request, EntityManagerInterface $manager, LocationService $service): Response {
        $form = Form::create();

        $content = (object)$request->request->all();

        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Un emplacement avec ce nom existe déjà");
        }

        if (!isset($content->client) && ($content->kiosk || $content->type ?? 0 == Location::CLIENT)) {
            $form->addError("client", "Requis pour les emplacements de type borne ou client");
        }
        if ($content->kiosk && (!isset($content->capacity) || $content->capacity < Location::MIN_KIOSK_CAPACITY)) {
            $form->addError("capacity", "La capacité ne peut être inférieure à " . Location::MIN_KIOSK_CAPACITY);
        }

        if ($form->isValid()) {
            $service->updateLocation(new Location(), $content);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Emplacement créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{location}", name="location_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function editTemplate(Location $location): Response {
        return $this->json([
            "submit" => $this->generateUrl("location_edit", ["location" => $location->getId()]),
            "template" => $this->renderView("referential/location/modal/edit.html.twig", [
                "location" => $location,
            ]),
            "success" => true
        ]);
    }

    /**
     * @Route("/modifier/{location}", name="location_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, LocationService $service, Location $location): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $location) {
            $form->addError("label", "Un autre emplacement avec ce nom existe déjà");
        }

        if (!isset($content->client) && ($content->kiosk || $content->type ?? 0 == Location::CLIENT)) {
            $form->addError("client", "Requis pour les emplacements de type borne ou client");
        }

        if ($content->kiosk && (!isset($content->capacity) || $content->capacity < Location::MIN_KIOSK_CAPACITY)) {
            $form->addError("capacity", "La capacité ne peut être inférieure à " . Location::MIN_KIOSK_CAPACITY);
        }

        if ($form->isValid()) {
            $service->updateLocation($existing, $content);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Emplacement modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{location}", name="location_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function deleteTemplate(Location $location): Response {
        return $this->json([
            "submit" => $this->generateUrl("location_delete", ["location" => $location->getId()]),
            "template" => $this->renderView("referential/location/modal/delete.html.twig", [
                "location" => $location,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{location}", name="location_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function delete(EntityManagerInterface $manager, Location $location): Response {
        if ($location->getOutClient() || !$location->getBoxRecords()->isEmpty() || !$location->getBoxes()->isEmpty()) {
            $location->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Emplacement <strong>{$location->getName()}</strong> désactivé avec succès"
            ]);
        } else {
            $originalLocation = $manager->getRepository(Location::class)->findOneBy([
                "deporte" => $location
            ]);

            if ($originalLocation) {
                $originalLocation->setOffset(null);
            }
            $manager->remove($location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Emplacement <strong>{$location->getName()}</strong> supprimé avec succès"
            ]);
        }
    }

    /**
     * @Route("/export", name="locations_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $locations = $manager->getRepository(Location::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $locations) {
            foreach ($locations as $location) {
                $location["locationType"] = $location["locationType"] ? Location::LOCATION_TYPES[$location["locationType"]] : '';
                $exportService->putLine($output, $location);
            }
        }, "export-emplacement-$today.csv", ExportService::LOCATION_HEADER);
    }

}
