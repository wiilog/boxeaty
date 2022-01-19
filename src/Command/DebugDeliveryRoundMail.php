<?php

namespace App\Command;

use App\Entity\ClientOrder;
use App\Entity\DeliveryRound;
use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class DebugDeliveryRoundMail extends Command {

    private const COMMAND_NAME = "debug:mail:delivery-round";

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public Mailer $mailer;

    /** @Required */
    public EntityManagerInterface $manager;

    public function __construct() {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure() {
        $this->setDescription("Send delivery round mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        $roundRepository = $this->manager->getRepository(DeliveryRound::class);
        $round = array_slice($roundRepository->findAll(), -1, 1)[0];
        $deliverer = $this->manager->find(User::class, 1);

        $content = $this->templating->render("emails/delivery_round.html.twig", [
            "deliveryRound" => $round,
            "ordersToDeliver" => $round->getSortedOrders(),
            "expectedDelivery" => Stream::from($round->getSortedOrders())
                ->map(fn(ClientOrder $order) => $order->getExpectedDelivery())
                ->min(),
        ]);

        $this->mailer->send($deliverer, "BoxEaty - Affectation de tournée", $content);

        return 0;
    }

}
