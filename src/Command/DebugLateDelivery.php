<?php

namespace App\Command;

use App\Entity\ClientOrder;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;

class DebugLateDelivery extends Command {

    private const COMMAND_NAME = "debug:mail:late-delivery";

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
        $this->setDescription("Send late delivery mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $order = $this->manager->find(ClientOrder::class, 34);

        $content = $this->templating->render("emails/mail_delivery_order.html.twig", [
            "order" => $order,
            "lateDelivery" => true,
            "late" => "tutute",
        ]);

        $this->mailer->send($order->getClient()->getContact(), "Retard de livraison", $content);

        return 0;
    }

}
