<?php

namespace App\Service;

use App\Entity\Client;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

}