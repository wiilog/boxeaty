<?php

namespace App\Command;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Import;
use App\Entity\Location;
use App\Entity\Quality;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ImportCommand extends Command {

    private const COMMAND_NAME = "app:launch:imports";

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public KernelInterface $kernel;

    private $data = null;
    private array $trace = [];
    private ?bool $hasError = null;
    private ?Import $import = null;

    public function __construct() {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure() {
        $this->setDescription("Launches imports");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $upcoming = $this->manager->getRepository(Import::class)->findUpcoming();

        $boxRepository = $this->manager->getRepository(Box::class);
        $qualityRepository = $this->manager->getRepository(Quality::class);
        $typeRepository = $this->manager->getRepository(BoxType::class);
        $clientRepository = $this->manager->getRepository(Client::class);
        $locationRepository = $this->manager->getRepository(Location::class);

        /** @var Import $import */
        foreach ($upcoming as $import) {
            $import->setStatus(Import::RUNNING)
                ->setCreations(0)
                ->setUpdates(0)
                ->setErrors(0);
        }

        $this->manager->flush();

        /** @var Import $import */
        foreach ($upcoming as $import) {
            $this->import = $import;
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
                if($stateValue && $state === false) {
                    $this->addError("Etat de box inconnu \"$state\"");
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

                if (!$this->hasError()) {
                    $box->setState($state);
                    $box->setQuality($quality);
                    $box->setType($type);
                    $box->setLocation($location);
                    $box->setOwner($owner);
                    $box->setComment($this->value(Import::COMMENT));

                    if (!$box->getId()) {
                        $this->manager->persist($box);
                        $creations++;
                    } else {
                        $updates++;
                    }
                }
            }

            $import->setStatus(Import::COMPLETED);
            $import->setCreations($creations);
            $import->setUpdates($updates);
            $import->setExecutionDate(new DateTime());
            $import->setTrace($this->saveTrace());

            fclose($handle);
        }

        $this->manager->flush();

        return 0;
    }

    private function value(string $column, bool $required = false): ?string {
        $value = $this->data[$this->import->getFieldsAssociation()[$column]] ?? null;
        if ($required && !$value) {
            $this->addError("Le champ " . Import::FIELDS[$column] . " est requis");
        }

        return $value;
    }

    private function nextLine($handle): bool {
        if ($this->data) {
            $this->trace[] = $this->data;
        }

        $this->data = fgetcsv($handle, 0, ";");
        $this->hasError = false;

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
        $path = $this->kernel->getProjectDir() . "/public/persistent/traces/$name";

        $file = fopen($path, "w");
        foreach ($this->trace as $line) {
            fputcsv($file, $line);
        }
        fclose($file);

        return $name;
    }

}
