<?php

namespace App\Command;

use App\Entity\ClientOrder;
use App\Entity\DeliveryRound;
use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use WiiCommon\Helper\Stream;
use Twig\Environment as Twig_Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class sendDeliveryRoundMail extends Command
{
    // id Cedric = 10
    private const COMMAND_NAME = "app:send:roundmail";

    /** @Required */
    public Twig_Environment $templating;
    /** @Required */
    public Mailer $mailer;

    /** @Required */
    public EntityManagerInterface $manager;

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure()
    {
        $this->setDescription("Send delivery round mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output):  int
    {

        $roundRepository = $this->manager->getRepository(DeliveryRound::class);
        $userRepository = $this->manager->getRepository(User::class);
        $round = $roundRepository->find(8);
        $deliverer = $userRepository->find(10);
        $orders = $this->manager->getRepository(ClientOrder::class)->findBy(["id" => [52, 50, 49]]);

        $content = $this->templating->render("emails/delivery_round.html.twig", [
            "deliveryRound" => $round,
            "ordersToDeliver" => $round->getSortedOrders(),
            "expectedDelivery" => Stream::from($orders)
                ->map(fn(ClientOrder $order) => $order->getExpectedDelivery())
                ->min(),
        ]);
        $this->mailer->send($deliverer, "BoxEaty - Affectation de tournée", $content);

        return 0;
    }
}
