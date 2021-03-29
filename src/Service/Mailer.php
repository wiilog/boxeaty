<?php

namespace App\Service;

use App\Entity\GlobalSetting;
use App\Entity\User;
use App\Helper\Stream;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer {

    /** @Required */
    public EntityManagerInterface $manager;

    private ?Address $address = null;
    private ?MailerInterface $mailer = null;

    private function getMailer(): ?MailerInterface {
        if($this->mailer) {
            return $this->mailer;
        }

        $config = $this->manager->getRepository(GlobalSetting::class)->getMailer();
        $host = $config[GlobalSetting::MAILER_HOST];
        $user = $config[GlobalSetting::MAILER_USER];
        $pass = $config[GlobalSetting::MAILER_PASSWORD];
        $port = $config[GlobalSetting::MAILER_PORT];

        if(!$host || !$port || !$user || !$pass) {
            return null;
        }

        $transport = Transport::fromDsn("smtp://$user:$pass@$host:$port");

        $this->mailer = new SymfonyMailer($transport);
        $this->address = new Address(
            $config[GlobalSetting::MAILER_SENDER_EMAIL],
            $config[GlobalSetting::MAILER_SENDER_NAME]
        );

        return $this->mailer;
    }

    public function send($recipients, string $subjet, string $content) {
        if (empty($recipients)) {
            return;
        }

        if(is_string($recipients)) {
            $originalRecipients = [$recipients];
        } else {
            if (!is_array($recipients) && !($recipients instanceof Collection)) {
                $recipients = [$recipients];
            }

            $originalRecipients = Stream::from($recipients)
                ->filter(fn($recipient) => $recipient)
                ->map(fn(User $user) => $user->getEmail())
                ->toArray();
        }

        if($_SERVER["APP_ENV"] === "prod" && empty($_SERVER["MAILS_REDIRECTION"])) {
            $emails = $originalRecipients;
        } else if(!empty($_SERVER["MAILS_REDIRECTION"])) {
            $emails = explode(";", $_SERVER["MAILS_REDIRECTION"]);
        }

        if (empty($emails)) {
            return;
        }

        if ($_SERVER["APP_ENV"] !== "prod") {
            $content .= "<p>DESTINATAIRES : ";
            $content .= Stream::from($originalRecipients)->join(', ') . "</p>";
        }

        $mailer = $this->getMailer();
        if(!$mailer) {
            return;
        }

        $email = (new Email())
            ->from($this->address)
            ->to(...$emails)
            ->subject($subjet)
            ->html($content);

        $mailer->send($email);
    }

}
