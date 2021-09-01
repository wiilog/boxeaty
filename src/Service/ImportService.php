<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Depository;
use App\Entity\Import;
use App\Entity\Location;
use App\Entity\Quality;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;

class ImportService {

    private const NOT_SELECTED = "-=!s/y~u0Un%KmH#|k5K9qk8;+7WM:kB";

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public KernelInterface $kernel;

    /** @Required */
    public ExportService $exportService;

    /** @Required */
    public Security $security;

    /** @Required */
    public BoxRecordService $boxRecordService;

    private $data = null;
    private array $trace = [];
    private ?bool $hasError = null;
    private ?Import $import = null;
    private int $creations;
    private int $updates;

    private array $boxStatesLower;

    public function execute(Import $import) {
        $this->boxStatesLower = array_map("strtolower", BoxStateService::BOX_STATES);

        $this->import = $import;
        $import->setCreations(0)
            ->setUpdates(0)
            ->setErrors(0);

        $handle = fopen("{$this->kernel->getProjectDir()}/public/persistent/imports/{$import->getFile()}", "r");
        $this->creations = 0;
        $this->updates = 0;

        $header = fgetcsv($handle, 0, ";");
        $header[] = "Erreurs";
        $this->trace[] = $header;

        while ($this->nextLine($handle)) {
            if ($import->getDataType() === Import::TYPE_BOX) {
                $this->importBox($import);
            } else if ($import->getDataType() === Import::TYPE_LOCATION) {
                $this->importLocation($import);
            }

            if ($this->creations % 500 == 0) {
                $this->manager->flush();
            }
        }

        $import->setStatus(Import::COMPLETED);
        $import->setCreations($this->creations);
        $import->setUpdates($this->updates);
        $import->setExecutionDate(new DateTime());
        $import->setTrace($this->saveTrace());

        $this->manager->flush();

        fclose($handle);
    }

    private function importBox(Import $import) {
        $boxRepository = $this->manager->getRepository(Box::class);
        $qualityRepository = $this->manager->getRepository(Quality::class);
        $typeRepository = $this->manager->getRepository(BoxType::class);
        $clientRepository = $this->manager->getRepository(Client::class);
        $locationRepository = $this->manager->getRepository(Location::class);

        $number = $this->value(Import::NUMBER, true);

        $box = $boxRepository->findOneBy(["number" => $number]);
        if (!$box) {
            $box = new Box();
            $box->setNumber($number);
        }

        $boxOrCrate = $this->value(Import::BOX_OR_CRATE, true, $box->isBox());
        $boxOrCrate = strtolower($boxOrCrate);
        if ($boxOrCrate === "box") {
            $isBox = true;
        } else if ($boxOrCrate === "caisse") {
            $isBox = false;
        } else {
            $this->addError("Le champ \"box ou caisse\" doit valoir \"box\" ou \"caisse\"");
        }

        $stateValue = $this->value(Import::STATE);
        $state = array_search(strtolower($stateValue), $this->boxStatesLower);
        if ($stateValue && $state === false) {
            $this->addError("Etat de Box inconnu \"$state\"");
        }

        $ownerValue = $this->value(Import::OWNER);
        $owner = $clientRepository->findOneBy(["name" => $ownerValue]);
        if ($ownerValue && !$owner) {
            $this->addError("Aucun client correspondant au propriétaire \"$ownerValue\"");
        }

        $qualityValue = $this->value(Import::QUALITY, true);
        $quality = $qualityRepository->findOneBy(["name" => $qualityValue]);
        if ($qualityValue && !$quality) {
            $this->addError("Aucune qualité correspondant à \"$qualityValue\"");
        }

        $typeValue = $this->value(Import::TYPE, true);
        $type = $typeRepository->findOneBy(["name" => $typeValue]);
        if ($typeValue && !$type) {
            $this->addError("Aucun type correspondant à \"$typeValue\"");
        }

        $locationValue = $this->value(Import::LOCATION, true);
        $location = $locationRepository->findOneBy(["name" => $locationValue]);
        if ($locationValue && !$location) {
            $this->addError("Aucun emplacement correspondant à \"$locationValue\"");
        }

        /** @var Client $owner */
        if ($import->getUser()->getRole()->isAllowEditOwnGroupOnly()) {
            $groups = $import->getUser()->getGroups();

            if ($groups->contains($location->getClient()->getGroup())) {
                $this->addError(
                    "Vous ne pouvez pas importer des Box dans " .
                    "l'emplacement \"$locationValue\" car il appartient au " .
                    "groupe \"{$location->getClient()->getGroup()->getName()}\""
                );
            }

            if (!$groups->contains($owner->getGroup())) {
                $this->addError(
                    "Vous ne pouvez pas importer des Box du " .
                    "client \"$ownerValue\" car il est dans le " .
                    "groupe \"{$owner->getGroup()->getName()}\""
                );
            }
        }

        if (!$this->hasError()) {
            $box->setIsBox($isBox)
                ->setType($type)
                ->setUses(0)
                ->setCanGenerateDepositTicket(false)
                ->setState($state)
                ->setLocation($location)
                ->setQuality($quality)
                ->setOwner($owner)
                ->setComment($this->value(Import::COMMENT));

            [$tracking, $record] = $this->boxRecordService->generateBoxRecords($box, null, $import->getUser());
            $this->boxRecordService->persist($box, $tracking);
            $this->boxRecordService->persist($box, $record);

            if (!$box->getId()) {
                $this->manager->persist($box);
                $this->creations++;
            } else {
                $this->updates++;
            }
        }
    }

    private function importLocation(Import $import) {
        $locationRepository = $this->manager->getRepository(Location::class);
        $clientRepository = $this->manager->getRepository(Client::class);
        $depositoryRepository = $this->manager->getRepository(Depository::class);

        $name = $this->value(Import::NAME, true);

        $location = $locationRepository->findOneBy(["name" => $name]);
        if (!$location) {
            $location = new Location();
            $location->setName($name);
        }

        $locationOrKiosk = $this->value(Import::LOCATION_OR_KIOSK, false, $location->isKiosk());
        $locationOrKiosk = strtolower($locationOrKiosk);
        if ($locationOrKiosk === "emplacement") {
            $kiosk = false;
        } else if ($locationOrKiosk === "borne") {
            $kiosk = true;
        } else {
            $this->addError("Le champ \"emplacement ou borne\" doit valoir \"emplacement\" ou \"borne\"");
        }

        if (isset($kiosk)) {
            $activeValue = $this->value(Import::ACTIVE, false, $location->isActive());
            $activeValue = strtolower($activeValue);
            if(in_array($activeValue, ["actif", "active", "oui", "1"])) {
                $active = true;
            } else if(in_array($activeValue, ["inactif", "inactive", "non", "0"])) {
                $active = false;
            } else {
                $this->addError("Le champ \"actif\" doit valoir \"actif\" ou \"inactif\"");
            }

            $description = $this->value(Import::DESCRIPTION, false, $location->getDescription());

            $defaultClient = $location->getClient() ? $location->getClient()->getName() : null;
            $clientValue = $this->value(Import::CLIENT, false, $defaultClient);
            $client = $clientRepository->findOneBy(["name" => $clientValue]);
            if ($clientValue && !$client) {
                $this->addError("Aucun client correspondant à \"$clientValue\"");
            }

            if ($kiosk) {
                $capacity = $this->value(Import::CAPACITY, false, $location->getCapacity());
                $message = $this->value(Import::MESSAGE, false, $location->getMessage());
            } else {
                $typeValue = $this->value(Import::TYPE);
                $type = array_search($typeValue, Location::LOCATION_TYPES);
                if ($typeValue && $type === false) {
                    $this->addError("Type d'emplacement inconnu \"$typeValue\"");
                }

                $depositoryValue = $this->value(Import::DEPOSITORY, true);
                $depository = $depositoryRepository->findOneBy(["name" => $depositoryValue]);
                if ($depositoryValue && !$depository) {
                    $this->addError("Aucun dépôt ne correspond à \"$depository\"");
                }
            }

            if ($import->getUser()->getRole()->isAllowEditOwnGroupOnly()) {
                $groups = $import->getUser()->getGroups();

                if ($groups->contains($location->getClient()->getGroup())) {
                    $this->addError(
                        "Vous ne pouvez pas importer l'emplacement " .
                        "\"$name\" car il appartient au groupe " .
                        "\"{$location->getClient()->getGroup()->getName()}\""
                    );
                }

                if (!$groups->contains($location->getClient()->getGroup())) {
                    $this->addError(
                        "Vous ne pouvez pas importer l'emplacement" .
                        "\"$name\" car il est dans le groupe " .
                        "\"{$location->getClient()->getGroup()->getName()}\""
                    );
                }
            }

            if (!$this->hasError()) {
                $location->setKiosk($kiosk)
                    ->setDescription($description)
                    ->setActive($active);

                if ($kiosk) {
                    $location
                        ->setCapacity($capacity)
                        ->setMessage($message);
                } else {
                    $location
                        ->setDepository($depository)
                        ->setType($type);
                }

                if (!$location->getId()) {
                    $this->manager->persist($location);
                    $this->creations++;
                } else {
                    $this->updates++;
                }
            }
        }
    }

    private function value(string $column, bool $required = false, $default = self::NOT_SELECTED): ?string {
        if (!isset($this->import->getFieldsAssociation()[$column]) && !$required) {
            return $default !== self::NOT_SELECTED ? $default : null;
        }

        $value = $this->data[$this->import->getFieldsAssociation()[$column] ?? null] ?? null;
        if ($required && !$value) {
            $this->addError("Le champ " . Import::FIELDS[$this->import->getDataType()][$column]["name"] . " est requis");
        }

        return $value;
    }

    private function nextLine($handle): bool {
        if ($this->data) {
            $this->trace[] = $this->data;
        }

        $this->data = fgetcsv($handle, 0, ";");
        $this->hasError = false;

        if ($this->data && $this->exportService->getEncoding() === ExportService::ENCODING_UTF8) {
            $this->data = array_map("utf8_encode", $this->data);
        }

        return $this->data !== false;
    }

    private function hasError(): bool {
        return $this->hasError;
    }

    private function addError(string $error) {
        $this->hasError = true;
        $this->import->setErrors($this->import->getErrors() + 1);
        $this->data[] = $error;
    }

    private function saveTrace(): ?string {
        if (empty($this->trace)) {
            return null;
        }

        $name = bin2hex(random_bytes(6)) . ".csv";
        $tracesDirectory = $this->kernel->getProjectDir() . "/public/persistent/traces";
        if (!is_dir($tracesDirectory)) {
            mkdir($tracesDirectory);
        }

        $path = $tracesDirectory . '/' . $name;

        $file = fopen($path, "w");
        foreach ($this->trace as $line) {
            $this->exportService->putLine($file, $line);
        }
        fclose($file);

        return $name;
    }

}
