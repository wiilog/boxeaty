<?php

namespace App\Command;

use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;

class DebugNewUser extends Command {

    private const COMMAND_NAME = "debug:mail:new-user";

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
        $this->setDescription("Send new user mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $userRepository = $this->manager->getRepository(User::class);
        $user = $userRepository->find(10);
        $recipients = $userRepository->findNewUserRecipients($user->getGroups()->first());

        $this->mailer->send(
            $recipients,
            "BoxEaty - Nouvel utilisateur",
            $this->templating->render("emails/new_user.html.twig", [
                "user" => $user,
            ])
        );

        return 0;
    }

}
