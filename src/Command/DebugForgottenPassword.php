<?php

namespace App\Command;

use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;

class DebugForgottenPassword extends Command {

    private const COMMAND_NAME = "debug:mail:forgotten-password";

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
        $this->setDescription("Send forgotten password mail");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $userRepository = $this->manager->getRepository(User::class);
        $user = $userRepository->find(10);
        $token = bin2hex(random_bytes(16));

        $this->mailer->send($user, "BoxEaty - Réinitialisation du mot de passe",
            $this->templating->render("emails/forgotten_password.html.twig", [
                "user" => $user,
                "token" => $token,
            ])
        );

        return 0;
    }

}
