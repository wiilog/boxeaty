<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Depository;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;

class LocationService {

    /** @Required */
    public EntityManagerInterface $manager;

    public function updateLocation(Location $location, StdClass $content): array {
        $client = isset($content->client) ? $this->manager->getRepository(Client::class)->find($content->client) : null;
        $depository = isset($content->depository) ? $this->manager->getRepository(Depository::class)->find($content->depository) : null;

        $location->setKiosk($content->kiosk)
            ->setName($content->name)
            ->setActive($content->active)
            ->setClient($client)
            ->setDescription($content->description ?? null)
            ->setDeposits(0);

        if ((int)$content->kiosk === 1) {
            $location->setCapacity($content->capacity ?? null)
                ->setMessage($content->message ?? null)
                ->setType(null)
                ->setDepository(null);
        } else {
            $location->setCapacity(null)
                ->setMessage(null)
                ->setType($content->type ?? null)
                ->setDepository($depository);
        }

        if(!$location->getOutClient() && ($content->kiosk || $content->type ?? null == Location::CLIENT)) {
            $offset = $this->copyOffset($location, new Location());
            $this->manager->persist($offset);
        } else if($location->getOutClient()) {
            $this->copyOffset($location, $location->getOffset());
        }

        $this->manager->persist($location);

        return [$location, $offset ?? null];
    }

    private function copyOffset(Location $from, Location $to): Location {
        $from->setOffset($to);

        return $to->setName("{$from->getName()}_deporte")
            ->setActive($from->isActive())
            ->setDepository($from->getDepository())
            ->setClient($from->getClient())
            ->setCapacity($from->getCapacity())
            ->setMessage(null)
            ->setType($from->getType())
            ->setKiosk($from->isKiosk());
    }

}
