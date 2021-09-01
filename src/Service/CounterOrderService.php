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
use WiiCommon\Helper\Stream;

class CounterOrderService {

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
                throw new BadRequestHttpException("Invalid counter order session token");
            }
        }

        return $this->token;
    }

    public function renderBoxes(): array {
        return [
            "submit" => $this->router->generate("counter_order_boxes_submit"),
            "template" => $this->twig->render("operation/counter_order/modal/boxes.html.twig", [
                "session" => $this->getToken(),
                "boxes" => $this->get(Box::class),
                "price" => $this->getBoxesPrice(),
            ])
        ];
    }

    public function renderDepositTickets(): array {
        return [
            "submit" => $this->router->generate("counter_order_deposit_tickets_submit"),
            "template" => $this->twig->render("operation/counter_order/modal/deposit_tickets.html.twig", [
                "session" => $this->getToken(),
                "tickets" => $this->get(DepositTicket::class),
                "price" => $this->getTicketsPrice(),
            ])
        ];
    }

    public function renderPayment(): array {
        return [
            "submit" => $this->router->generate("counter_order_confirm"),
            "template" => $this->twig->render("operation/counter_order/modal/payment.html.twig", [
                "session" => $this->getToken(),
                "boxes" => $this->get(Box::class),
                "tickets" => $this->get(DepositTicket::class),
                "total_price" => $this->getBoxesPrice() - $this->getTicketsPrice(),
            ])
        ];
    }

    public function renderConfirmation(): array {
        return [
            "template" => $this->twig->render("operation/counter_order/modal/confirmation.html.twig"),
        ];
    }

    public function getBoxesPrice(): string {
        return Stream::from($this->get(Box::class))
            ->reduce(fn(int $total, Box $box) => $total + $box->getType()->getPrice());
    }

    public function getTicketsPrice(): string {
        return Stream::from($this->get(DepositTicket::class))
            ->reduce(function(int $total, DepositTicket $ticket) {
                return $total + $ticket->getBox()->getType()->getPrice();
            });
    }

    /**
     * @return array|Box[]|DepositTicket[]
     */
    public function get(string $class): array {
        $id = $this->getToken();

        if(!isset($this->$class)) {
            $this->$class = $this->manager
                ->getRepository($class)
                ->findBy(["number" => $this->session->get("counter_order.$id.$class", [])]);
        }

        return $this->$class;
    }

    public function update(string $class) {
        $id = $this->getToken();
        $items = $this->request->getCurrentRequest()->request->get("items");

        $this->session->set("counter_order.$id", [$id, new DateTime()]);
        $this->session->set("counter_order.$id.$class", array_filter(explode(",", $items)));
    }

    public function clear($current = false) {
        //remove current counter order
        if ($current) {
            $id = $this->getToken();

            $this->session->remove("counter_order.$id");
            foreach ([Box::class, DepositTicket::class] as $class) {
                $this->session->remove("counter_order.$id.$class");
            }
        }

        //clear previous unfinished orders
        $expiry = new DateTime("-1 day");
        foreach ($this->session->all() as $key => $value) {
            if (preg_match("/^counter_order\.[A-Z0-9]{8,32}$/i", $key)) {
                [$id, $date] = $value;
                if ($date < $expiry) {
                    $this->session->remove("counter_order.$id");
                    $this->session->remove("counter_order.$id.boxes");
                    $this->session->remove("counter_order.$id.tickets");
                }
            }
        }
    }

}
