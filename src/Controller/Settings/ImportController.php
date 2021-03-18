<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Import;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Helper\StringHelper;
use App\Repository\ImportRepository;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/imports")
 */
class ImportController extends AbstractController {

    /**
     * @Route("/liste", name="imports_list")
     * @HasPermission(Role::MANAGE_IMPORTS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("settings/import/index.html.twig", [
            "new_import" => new Import(),
            "initial_imports" => $this->api($request, $manager)->getContent(),
            "imports_order" => ImportRepository::DEFAULT_DATATABLE_ORDER,
        ]);
    }

    /**
     * @Route("/api", name="imports_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_IMPORTS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $imports = $manager->getRepository(Import::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];
        foreach ($imports["data"] as $import) {
            $data[] = [
                "id" => $import->getId(),
                "name" => $import->getName(),
                "status" => Import::NAMES[$import->getStatus()],
                "creationDate" => FormatHelper::datetime($import->getCreationDate()),
                "executionDate" => FormatHelper::datetime($import->getExecutionDate()),
                "creations" => $import->getCreations(),
                "updates" => $import->getUpdates(),
                "errors" => $import->getErrors(),
                "user" => $import->getUser()->getUsername(),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "cancellable" => $import->getStatus() === Import::UPCOMING,
                    "trace" => true,
                    "import" => $import,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $imports["total"],
            "recordsFiltered" => $imports["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="import_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_IMPORTS)
     */
    public function new(Request $request, SessionInterface $session, KernelInterface $kernel): Response {
        $form = Form::create();

        $content = (object)$request->request->all();

        if ($form->isValid()) {
            /** @var UploadedFile $file */
            $file = $request->files->get("attachment");

            $csv = explode("\n", $file->getContent());
            if (count($csv) < 2) {
                return $this->json([
                    "success" => true,
                    "message" => "Le fichier doit contenir au moins une ligne d'en-tête et une ligne d'exemple",
                ]);
            }

            $fields = explode(";", $csv[0]);
            $values = explode(";", $csv[1]);

            $name = bin2hex(random_bytes(6)) . "." . $file->getClientOriginalExtension();
            $file->move($kernel->getProjectDir() . "/public/persistent/imports", $name);

            $import = new Import();
            $import->setName($content->name)
                ->setStatus(count($csv) >= 101 ? Import::UPCOMING : Import::INSTANT)
                ->setDataType($content->type)
                ->setCreationDate(new DateTime())
                ->setFile($name);

            $session->set("draft-import", $import);

            $preAssignments = [];
            foreach (Import::FIELDS as $code => $config) {
                $closest = null;
                $closestDistance = PHP_INT_MAX;

                foreach ($fields as $fileField) {
                    $distance = StringHelper::levenshtein($fileField, $config["name"]);
                    if ($distance < 5 && $distance < $closestDistance) {
                        $closest = $fileField;
                        $closestDistance = $distance;
                    }
                }

                if ($closest) {
                    $preAssignments[$closest] = $code;
                }
            }

            return $this->json([
                "success" => true,
                "modal" => $this->renderView("settings/import/modal/fields_association.html.twig", [
                    "fields" => Import::FIELDS,
                    "pre_assignments" => $preAssignments,
                    "file_fields" => Stream::from($fields)
                        ->map(fn($field, $id) => [
                            "name" => utf8_encode(trim($field)),
                            "value" => utf8_encode(trim($values[$id] ?? "")),
                            "closest" => $preAssignments[$field] ?? null,
                        ])
                        ->toArray()
                ]),
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/association-champs", name="import_fields_association", options={"expose": true})
     * @HasPermission(Role::MANAGE_IMPORTS)
     */
    public function fieldsAssociation(Request $request, SessionInterface $session,
                                      EntityManagerInterface $manager, ImportService $importService): Response {
        $import = $session->get("draft-import");
        if (!$import) {
            return $this->json([
                "success" => true,
                "message" => "Erreur lors de la création de l'import",
            ]);
        }

        $form = Form::create();

        $content = (object)$request->request->all();

        if ($form->isValid()) {
            $associations = explode(",", $content->associations);

            foreach(Import::FIELDS as $name => $config) {
                if(isset($config["required"]) && $config["required"] && !in_array($name, $associations)) {
                    return $this->json([
                        "success" => false,
                        "message" => "Le champ {$config['name']} est requis mais n'est associé à aucune colonne du fichier",
                    ]);
                }
            }
            $import->setFieldsAssociation(array_flip($associations))
                ->setUser($this->getUser());

            if($import->getStatus() === Import::INSTANT) {
                $importService->execute($import);
            }

            $manager->persist($import);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Import créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/annuler", name="import_cancel", options={"expose": true})
     * @HasPermission(Role::MANAGE_IMPORTS)
     */
    public function cancel(Request $request, EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $import = $manager->getRepository(Import::class)->find($content->import);
        if (!$import) {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Cet import n'existe pas",
            ]);
        }

        if ($import->getStatus() !== Import::UPCOMING) {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Seul les imports planifiés peuvent être annulés",
            ]);
        }

        $import->setStatus(Import::CANCELLED);
        $manager->flush();

        return $this->json([
            "success" => true,
            "reload" => true,
            "message" => "Import annulé avec succès",
        ]);
    }

}
