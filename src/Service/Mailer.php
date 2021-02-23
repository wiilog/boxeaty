<?php

namespace App\Service;

use App\Entity\User;
use App\Helper\Stream;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class Mailer {

    /** @Required  */
    public MailerInterface $mailer;

    public function send($recipients, string $subjet, string $content) {
        if($_SERVER["APP_ENV"] === "prod" && !$_SERVER["MAILS_REDIRECTION"]) {
            if (!is_array($recipients) && !($recipients instanceof Collection)) {
                $recipients = [$recipients];
            }

            $emails = Stream::from($recipients)
                ->map(fn(User $user) => $user->getEmail())
                ->toArray();
        } else {
            $emails = explode(";", $_SERVER["MAILS_REDIRECTION"]);
        }

        if (empty($emails)) {
            return;
        }

        $email = (new Email())
            ->from("noreply@follow-gt.fr")
            ->to(...$emails)
            ->subject($subjet)
            ->html($content);

        $this->mailer->send($email);
    }

}
