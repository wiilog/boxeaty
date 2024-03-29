<?php

namespace App\Command;

use App\Entity\DepositTicket;
use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;

class DebugDepositTicket extends Command {

    private const COMMAND_NAME = "debug:mail:deposit-ticket";

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
        $this->setDescription("Send deposit ticket mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $userRepository = $this->manager->getRepository(User::class);
        $user = $userRepository->find(10);
        $depositTicketRepository = $this->manager->getRepository(DepositTicket::class);
        $ticket = $depositTicketRepository->find(1);
        $usable = "tout le réseau BoxEaty";
        $this->mailer->send(
            $user->getEmail(),
            "BoxEaty - Ticket‑consigne",
            $this->templating->render("emails/deposit_ticket.html.twig", [
                "ticket" => $ticket,
                "usable" => $usable,
            ])
        );
        return 0;
    }

}
