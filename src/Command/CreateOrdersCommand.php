<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\OrderType;
use App\Entity\Status;
use App\Helper\FormatHelper;
use App\Service\ClientOrderService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateOrdersCommand extends Command {

    private const COMMAND_NAME = "app:create-orders";

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public ClientOrderService $clientOrderService;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    public function __construct() {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure() {
        $this->setDescription("Create client orders");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $now = new DateTime("today midnight");
        $max = new DateTime("+1 month midnight");
        $days = FormatHelper::ENGLISH_WEEK_DAYS;

        $clients = $this->manager->getRepository(Client::class)->findActiveRecurrence();
        if(!$clients) {
            $output->writeln("No orders to create for the {$now->format('d/m/Y')}");
            return 0;
        }

        $planned = $this->manager->getRepository(Status::class)->findOneByCode(Status::CODE_DELIVERY_PLANNED);

        /** @var Client $client */
        foreach($clients as $client) {
            $recurrence = $client->getClientOrderInformation()->getOrderRecurrence();

            $start = clone $recurrence->getStart();
            $start->setTime(0, 0);

            $end = clone $recurrence->getEnd();
            $end->setTime(0, 0);
            if($end > $max) {
                $end = $max;
            }

            $date = clone $start;
            $date->modify("{$days[$recurrence->getDay() + 1]} this week");
            if($date < $start) {
                $date = clone $start;
            }

            while($date != $now && $date <= $end) {
                $date->modify("{$days[$recurrence->getDay() + 1]} this week +{$recurrence->getPeriod()} weeks");
            }

            if($date <= $end) {
                $type = $this->manager->getRepository(OrderType::class)->findOneByCode(OrderType::AUTONOMOUS_MANAGEMENT);

                $order = (new ClientOrder())
                    ->setAutomatic(true)
                    ->setType($type)
                    ->setClient($client)
                    ->setNumber($this->uniqueNumberService->createUniqueNumber(ClientOrder::class))
                    ->setDeliveryRound(null)
                    ->setRequester(null)
                    ->setExpectedDelivery($date)
                    ->setCollectRequired(false)
                    ->setCollect(null)
                    ->setDepository($client->getClientOrderInformation()->getDepository())
                    ->setDeliveryMethod($client->getClientOrderInformation()->getDeliveryMethod())
                    ->setTokensAmount($client->getClientOrderInformation()->getTokenAmount() ?? 0)
                    ->setDeliveryPrice($recurrence->getDeliveryFlatRate())
                    ->setServicePrice($recurrence->getServiceFlatRate())
                    ->setRequester($client->getContact())
                    ->setValidatedAt(new DateTime())
                    ->setCreatedAt(new DateTime())
                    ->setComment($client->getClientOrderInformation()->getComment());

                foreach($client->getCratePatternLines() as $pattern) {
                    $line = (new ClientOrderLine())
                        ->setBoxType($pattern->getBoxType())
                        ->setQuantity($pattern->getQuantity() * $recurrence->getCrateAmount())
                        ->setUnitPrice($pattern->getUnitPrice())
                        ->setClientOrder($order);

                    $order->addLine($line);
                }

                $forSplitting = $order->getLines()->map(fn(ClientOrderLine $line) => [
                    "boxType" => $line->getBoxType()->getId(),
                    "quantity" => $line->getQuantity(),
                ])->toArray();

                $order->setCratesAmount(count($this->clientOrderService->getCartSplitting($this->manager, $client, $forSplitting)));

                $this->clientOrderService->updateClientOrderStatus($order, $planned, $client->getContact());

                $this->manager->persist($order);
                $output->writeln("Created order N°{$order->getNumber()}");
            }
        }

        $this->manager->flush();

        return 0;
    }

}
