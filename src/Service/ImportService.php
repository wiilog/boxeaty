<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Import;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\BoxRecord;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;

class ImportService {

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

    public function execute(Import $import) {
        $this->import = $import;
        $import->setCreations(0)
            ->setUpdates(0)
            ->setErrors(0);

        $boxRepository = $this->manager->getRepository(Box::class);
        $qualityRepository = $this->manager->getRepository(Quality::class);
        $typeRepository = $this->manager->getRepository(BoxType::class);
        $clientRepository = $this->manager->getRepository(Client::class);
        $locationRepository = $this->manager->getRepository(Location::class);

        $handle = fopen("{$this->kernel->getProjectDir()}/public/persistent/imports/{$import->getFile()}", "r");
        $creations = 0;
        $updates = 0;

        $header = fgetcsv($handle, 0, ";");
        $header[] = "Erreurs";
        $this->trace[] = $header;

        while ($this->nextLine($handle)) {
            $number = $this->value(Import::NUMBER, true);

            $box = $boxRepository->findOneBy(["number" => $number]);
            if (!$box) {
                $box = new Box();
                $box->setNumber($number);
            }

            $stateValue = $this->value(Import::STATE);
            $state = array_search($stateValue, Box::NAMES);
            if ($stateValue && $state === false) {
                $this->addError("Etat de Box inconnu \"$state\"");
            }

            $ownerValue = $this->value(Import::OWNER, true);
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
                $box->setType($type)
                    ->setUses(0)
                    ->setCanGenerateDepositTicket(false)
                    ->setState($state)
                    ->setLocation($location)
                    ->setQuality($quality)
                    ->setOwner($owner)
                    ->setComment($this->value(Import::COMMENT));

                [$tracking, $record] = $this->boxRecordService->generateBoxRecords($box, [], $import->getUser());

                if ($tracking) {
                    $tracking->setBox($box);
                    $this->manager->persist($tracking);
                }

                if ($record) {
                    $record->setBox($box);
                    $this->manager->persist($record);
                }

                if (!$box->getId()) {
                    $this->manager->persist($box);
                    $creations++;
                } else {
                    $updates++;
                }
            }

            if ($creations % 500 == 0) {
                $this->manager->flush();
            }
        }

        $import->setStatus(Import::COMPLETED);
        $import->setCreations($creations);
        $import->setUpdates($updates);
        $import->setExecutionDate(new DateTime());
        $import->setTrace($this->saveTrace());

        $this->manager->flush();

        fclose($handle);
    }

    private function value(string $column, bool $required = false): ?string {
        $value = $this->data[$this->import->getFieldsAssociation()[$column] ?? null] ?? null;
        if ($required && !$value) {
            $this->addError("Le champ " . Import::FIELDS[$column]["name"] . " est requis");
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
