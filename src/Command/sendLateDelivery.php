<?php

namespace App\Command;

use App\Entity\ClientOrder;
use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Twig\Environment as Twig_Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class sendLateDelivery extends Command
{
    private const COMMAND_NAME = "app:send:LateDeliveryMail";

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public Mailer $mailer;

    /** @Required */
    public EntityManagerInterface $manager;

    public function __construct(string $name = null)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure()
    {
        $this->setDescription("Send late delivery mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userRepository = $this->manager->getRepository(User::class);
        $user = $userRepository->find(10);
        $orderRepository = $this->manager->getRepository(ClientOrder::class);
        $order = $orderRepository->find(34);

        $content = $this->templating->render("emails/mail_delivery_order.html.twig", [
            "order" => $order,
            "lateDelivery" => true,
            "late" => "tutute",
        ]);

        $this->mailer->send($order->getClient()->getContact(), "Retard de livraison", $content);

        return 0;
    }

}
