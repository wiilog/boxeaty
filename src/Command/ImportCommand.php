<?php

namespace App\Command;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Import;
use App\Entity\Location;
use App\Entity\Quality;
use App\Service\ExportService;
use App\Service\ImportService;
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
    public ImportService $importService;

    public function __construct() {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure() {
        $this->setDescription("Launches imports");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $upcoming = $this->manager->getRepository(Import::class)->findUpcoming();

        /** @var Import $import */
        foreach ($upcoming as $import) {
            $import->setStatus(Import::RUNNING)
                ->setCreations(0)
                ->setUpdates(0)
                ->setErrors(0);
        }

        $this->manager->flush();

        foreach($upcoming as $import) {
            $this->importService->execute($import);
        }

        $this->manager->flush();

        return 0;
    }

}
