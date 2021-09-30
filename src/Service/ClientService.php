<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\CratePatternLine;
use App\Entity\OrderRecurrence;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WiiCommon\Helper\Stream;

class ClientService {

    /** @Required */
    public HttpClientInterface $client;

    public function updateCoordinates(Client $client, $address): bool {
        $address = urlencode($address);
        $response = $this->client->request("GET", "https://nominatim.openstreetmap.org/search?format=json&q=$address");

        try {
            $content = json_decode($response->getContent());
            if (count($content) === 0) {
                return false;
            }

            $client->setLatitude($content[0]->lat)
                ->setLongitude($content[0]->lon);

            return true;
        } catch (Exception $ignored) {
            return false;
        }
    }

    public function recalculateMonthlyPrice($entity) {
        if($entity instanceof OrderRecurrence) {
            $client = $entity->getClientOrderInformation()->getClient();
            $recurrence = $entity;
        } else if($entity instanceof Client){
            $client = $entity;
            $recurrence = $client->getClientOrderInformation()->getOrderRecurrence();
        } else if($entity instanceof CratePatternLine) {
            $client = $entity->getClient();
            $recurrence = $client->getClientOrderInformation()->getOrderRecurrence();
        } else {
            throw new \RuntimeException("Unsupported entity");
        }

        if($recurrence) {
            if($client->getCratePatternLines()->isEmpty()) {
                $recurrence->setMonthlyPrice(0);
                return;
            }

            $cratePrice = Stream::from($client->getCratePatternLines())
                ->map(fn(CratePatternLine $line) => $line->getQuantity() * $line->getUnitPrice())
                ->sum();

            $singleOrderPrice = $cratePrice * $recurrence->getCrateAmount() + $recurrence->getDeliveryFlatRate() + $recurrence->getServiceFlatRate();

            $diff = $recurrence->getStart()->diff($recurrence->getEnd(), true);
            $totalOrderCount = floor(($diff->days / 7) / $recurrence->getPeriod());
            $months = $diff->days / 30.5;
dump($singleOrderPrice, $totalOrderCount, $months);
            $recurrence->setMonthlyPrice($singleOrderPrice * $totalOrderCount / $months);
        }
    }

}
