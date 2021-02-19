<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\Kiosk;
use App\Entity\Role;
use App\Helper\Form;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/referentiel/bornes")
 */
class KioskController extends AbstractController {

    /**
     * @Route("/liste", name="kiosks_list")
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function list(EntityManagerInterface $manager): Response {
        $clients = $manager->getRepository(Client::class)->findAll();

        return $this->render("referential/kiosk/index.html.twig", [
            "new_kiosk" => new Kiosk(),
            "clients" => $clients
        ]);
    }

    /**
     * @Route("/api", name="kiosks_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $kiosks = $manager->getRepository(Kiosk::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($kiosks["data"] as $kiosk) {
            $data[] = [
                "id" => $kiosk->getId(),
                "name" => $kiosk->getName(),
                "client" => $kiosk->getClient() ? $kiosk->getClient()->getName() : '',
                "actions" => $this->renderView("referential/kiosk/datatable_actions.html.twig"),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $kiosks["total"],
            "recordsFiltered" => $kiosks["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="kiosk_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Kiosk::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Cette borne existe déjà");
        }

        $client = $manager->getRepository(Client::class)->find($content->client);

        if($form->isValid()) {
            $kiosk = new Kiosk();
            $kiosk->setName($content->name)
                ->setClient($client);

            $manager->persist($kiosk);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Borne créée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{kiosk}", name="kiosk_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function editTemplate(Kiosk $kiosk): Response {
        return $this->json([
            "submit" => $this->generateUrl("kiosk_edit", ["kiosk" => $kiosk->getId()]),
            "template" => $this->renderView("referential/kiosk/modal/edit.html.twig", [
                "kiosk" => $kiosk,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{kiosk}", name="kiosk_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Kiosk $kiosk): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Kiosk::class)->findOneBy(["name" => $content->name]);
        $client = $manager->getRepository(Client::class)->find($content->client);
        if ($existing !== null && $existing !== $kiosk) {
            $form->addError("name", "Une autre borne avec ce nom existe déjà");
        }

        if($form->isValid()) {
            $kiosk->setName($content->name)
                ->setClient($client);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Borne modifiée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/export", name="kiosks_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_KIOSKS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $kiosks = $manager->getRepository(Kiosk::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $header = array_merge([
            "Nom de la borne",
            "Client",
        ]);

        return $exportService->export(function($output) use ($exportService, $kiosks) {
            foreach ($kiosks as $kiosk) {
                $exportService->putLine($output, $kiosk);
            }
        }, "export-bornes-$today.csv", $header);
    }

}