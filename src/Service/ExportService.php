<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Depository;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WiiCommon\Helper\Stream;

class ExportService {

    public const ENTITY_NAME = [
        Box::class => "Box",
        BoxRecord::class => "Mouvements",
        DepositTicket::class => "Tickets-consigne",
        "OneClient" => "Client",
        "ClientOrderHeaderOneTime" => "Commandes prestation ponctuelle",
        "ClientOrderHeaderAutonomousManagement" => "Commandes prestation autonome",
        "ClientOrderTrade" => "Commandes d'achat négoce",
        "ClientOrderRecurrent" => "Commandes récurrentes",
        "Indicator" => "Indicateurs",
        Client::class => "Clients",
        Group::class => "Groupes",
        Location::class => "Emplacements",
        BoxType::class => "Types de Box",
        Depository::class => "Dépôts",
        User::class => "Utilisateurs",
        Role::class => "Rôles",
        Quality::class => "Qualités",
    ];

    public const CLIENT_ORDER_MATCHES = [
        "ClientOrderHeaderOneTime",
        "ClientOrderHeaderAutonomousManagement",
        "ClientOrderTrade",
        "ClientOrderRecurrent",
    ];

    public const USER_HEADER = [
        "Nom d'utilisateur",
        "Adresse email",
        "Rôle",
        "Actif",
        "Dernière connexion",
    ];

    public const CLIENT_HEADER = [
        "Nom Client",
        "Actif",
        "Adresse",
        "Contact attribué",
        "Groupe",
        "Multi-site lié",
        "Multi-site",
    ];

    public const GROUP_HEADER = [
        "Nom de groupe",
        "Actif",
    ];

    public const LOCATION_HEADER = [
        "Type",
        "Nom de l'emplacement",
        "Dépôt",
        "Nom du client",
        "Actif",
        "Description",
        "Capacité",
        "Type d'emplacement",
        "Nombre de contenants",
    ];

    public const MOVEMENT_HEADER = [
        "Date",
        "Emplacement",
        "Numéro Box",
        "Qualité",
        "Etat",
        "Client",
        "Utilisateur",
    ];

    public const BOX_TYPE_HEADER = [
        "Type de Box / Caisse",
        "Prix",
        "Actif",
        "Contenance",
        "Forme",
    ];

    public const DEPOSITORY_HEADER = [
        "Nom du dépôt",
        "Statut",
        "Description",
    ];

    public const ROLE_HEADER = [
        "Nom",
        "Actif",
    ];

    public const QUALITY_HEADER = [
        "Nom",
        "Actif",
    ];

    public const CLIENT_ORDER_HEADER_ONE_TIME = [
        "Numéro de commande",
        "Type de Box",
        "Nombre de Box livrées",
        "Nombre de jetons livrés",
        "Nombre de Box cassées",
        "Coût unitaire",
        "Moyen de paiement",
        "Montant facturé par livraison jour ouvré",
        "Montant facturé par livraison jour non ouvré",
        "Frais livraison transporteur",
        "Nombre de consignes utilisées",
        "Commande automatique",
    ];

    public const CLIENT_ORDER_HEADER_AUTONOMOUS_MANAGEMENT = [
        "Numéro de commande",
        "Nombre de Box mis à disposition",
        "Coût abonnement mensuel",
        "Montant facturé par livraison jour ouvré",
        "Montant facturé par livraison jour non ouvré",
        "Frais livraison transporteur",
        "Moyen de paiement",
        "Nombre de jetons livrés",
        "Nombre de caisses",
        "Montant de la caisse",
        "Commande automatique",
    ];

    public const CLIENT_ORDER_TRADE = [
        "Numéro de commande",
        "Type de Box",
        "Nombre de Box",
        "Tarif unitaire",
        "Montant kit de démarrage",
        "Montant facturé par livraison jour ouvré",
        "Montant facturé par livraison jour non ouvré",
        "Frais livraison transporteur",
        "Commande automatique",
    ];

    public const TICKET_HEADER = [
        "Date de création",
        "Lieu de création",
        "Date de validité",
        "Numéro de consigne",
        "Date et heure d'utilisation de la consigne",
        "Montant de la consigne",
        "Emplacement de la consigne",
        "Utilisateur en caisse",
        "Etat",
    ];

    public const BOX_HEADER = [
        "Code",
        "Date de création",
        "Box ou caisse",
        "Emplacement",
        "Etat",
        "Qualité",
        "Propriétaire",
        "Type",
    ];

    public const INDICATOR_HEADER = [
        "Quantité de déchets évités",
        "Nombre de contenants utilisés",
        "Distance parcourue en mobilité douce",
        "Distance parcourue en véhicule à moteur",
        "Taux de retour",
    ];

    public const ENCODING_UTF8 = "UTF8";
    public const ENCODING_WINDOWS = "WINDOWS";

    private EntityManagerInterface $manager;
    private ?string $encoding;

    public function __construct(EntityManagerInterface $manager) {
        $this->manager = $manager;
        $this->encoding = $manager->getRepository(GlobalSetting::class)
            ->getValue(GlobalSetting::CSV_EXPORTS_ENCODING);
    }

    public function getEncoding(): ?string {
        return $this->encoding;
    }

    public function export(callable $generator, string $name, ?array $headers = null): StreamedResponse {
        $response = new StreamedResponse(function() use ($generator, $headers) {
            $output = fopen("php://output", "wb");
            if($headers) {
                $firstCell = $headers[0] ?? null;
                if(is_array($firstCell)) {
                    foreach($headers as $headerLine) {
                        $this->putLine($output, $headerLine);
                    }
                } else {
                    $this->putLine($output, $headers);
                }
            }

            $generator($output);
            fclose($output);
        });

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name);

        $response->headers->set("Content-Type", "text/csv");
        $response->headers->set("Content-Disposition", $disposition);
        return $response;
    }

    public function putLine($handle, array $row) {
        $row = $this->stringify($row);

        if($this->encoding === self::ENCODING_UTF8) {
            $encodedRow = $row;
        } else if($this->encoding === self::ENCODING_WINDOWS) {
            $encodedRow = array_map("utf8_decode", $row);
        } else {
            throw new RuntimeException("Unknown encoding \"$this->encoding\"");
        }

        fputcsv($handle, $encodedRow, ";");
    }

    public function addWorksheet(Spreadsheet $spreadsheet, string $class, array $header, callable $transformer = null, array $options = [], bool $fromClient = false): Worksheet {
        $sheet = new Worksheet(null, $fromClient ? self::ENTITY_NAME["OneClient"] : self::ENTITY_NAME[$class]);
        $class = in_array($class, self::CLIENT_ORDER_MATCHES) ? ClientOrder::class : $class;
        if (class_exists($class)) {
            $repository = $this->manager->getRepository($class);
            if(!method_exists($repository, "iterateAll")) {
                throw new RuntimeException("The $class repository must have an iterateAll method to be exported");
            }
            $export = Stream::from($repository->iterateAll(...$options))
                ->map(function($row) use ($transformer) {
                    if($transformer) {
                        $row = $transformer($row);
                    }

                    return $this->stringify($row);
                })
                ->prepend($header)
                ->toArray();
        }
        else {
            $export = Stream::from(...$options)
                ->prepend($header)
                ->toArray();
        }

        $sheet->fromArray($export);
        $spreadsheet->addSheet($sheet);
        return $sheet;
    }

    public function stateMapper(?array $labels): callable {
        return function(array $row) use ($labels) {
            $row["state"] = isset($row["state"]) ? ($labels[$row["state"]] ?? "") : "";
            return $row;
        };
    }

    public function stringify(array $row): array {
        return array_map(function($cell) {
            if($cell instanceof DateTime) {
                return $cell->format("d/m/Y H:i:s");
            } else if(is_bool($cell)) {
                return $cell ? 'oui' : 'non';
            } else {
                return $cell;
            }
        }, $row);
    }

}
