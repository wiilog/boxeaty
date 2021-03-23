<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\DepositTicket;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class OrderService {

    /** @Required */
    public SessionInterface $session;

    /** @Required */
    public RequestStack $request;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public EntityManagerInterface $manager;

    private ?string $token = null;

    public function getToken(): ?string {
        if (!$this->token) {
            $this->token = $this->request->getCurrentRequest()->get("session");

            if (!preg_match("/^[A-Z0-9]{8,32}$/i", $this->token)) {
                throw new BadRequestHttpException("Invalid order session token");
            }
        }

        return $this->token;
    }

    public function renderBoxes(): array {
        return [
            "submit" => $this->router->generate("order_boxes_submit"),
            "template" => $this->twig->render("tracking/order/modal/boxes.html.twig", [
                "session" => $this->getToken(),
                "boxes" => $this->get(Box::class),
            ])
        ];
    }

    public function renderDepositTickets(): array {
        return [
            "submit" => $this->router->generate("order_deposit_tickets_submit"),
            "template" => $this->twig->render("tracking/order/modal/deposit_tickets.html.twig", [
                "session" => $this->getToken(),
                "tickets" => $this->get(DepositTicket::class),
            ])
        ];
    }

    public function renderPayment(): array {
        return [
            "submit" => $this->router->generate("order_payment_submit"),
            "template" => $this->twig->render("tracking/order/modal/payment.html.twig", [
                "session" => $this->getToken(),
                "boxes" => $this->get(Box::class),
                "tickets" => $this->get(DepositTicket::class),
            ])
        ];
    }

    public function renderConfirmation(): array {
        return [
            "template" => $this->twig->render("tracking/order/modal/confirmation.html.twig"),
        ];
    }

    /**
     * @return array|Box[]|DepositTicket[]
     */
    public function get(string $class): array {
        $id = $this->getToken();

        return $this->manager
            ->getRepository($class)
            ->findBy(["number" => $this->session->get("order.$id.$class", [])]);
    }

    public function update(string $class) {
        $id = $this->getToken();
        $items = $this->request->getCurrentRequest()->request->get("items");

        $this->session->set("order.$id", [$id, new DateTime()]);
        $this->session->set("order.$id.$class", array_filter(explode(",", $items)));
    }

    public function clear($current = false) {
        //remove current order
        if($current) {
            $id = $this->getToken();

            $this->session->remove("order.$id");
            foreach ([Box::class, DepositTicket::class] as $class) {
                $this->session->remove("order.$id.$class");
            }
        }

        //clear previous unfinished orders
        $expiry = new DateTime("-1 day");
        foreach($this->session->all() as $key => $value) {
            if(preg_match("/^order\.[A-Z0-9]{8,32}$/i", $key)) {
                [$id, $date] = $value;
                if($date < $expiry) {
                    $this->session->remove("order.$id");
                    $this->session->remove("order.$id.boxes");
                    $this->session->remove("order.$id.tickets");
                }
            }
        }
    }

}
