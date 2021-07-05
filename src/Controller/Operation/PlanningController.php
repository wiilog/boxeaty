<?php


namespace App\Controller\Operation;

use App\Entity\ClientOrder;
use App\Entity\Role;
use App\Annotation\HasPermission;
use App\Entity\Status;
use App\Helper\FormatHelper;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/operations/planification")
 */
class PlanningController extends AbstractController {

    /**
     * @Route("/", name="planning")
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("operation/planning/index.html.twig", [
            "content" => $this->content($request, $manager, false)->getContent(),
        ]);
    }

    /**
     * @Route("/contenu", name="planning_content", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function content(Request $request, EntityManagerInterface $manager, bool $json = true): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        if($request->query->has("from")) {
            $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        } else {
            $from = new DateTime();
        }

        if($request->query->has("to")) {
            $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));
        } else {
            $to = (clone $from)->modify("+20 days");
        }

        //group orders by date
        $ordersByDate = [];
        foreach ($clientOrderRepository->findBetween($from, $to) as $order) {
            $ordersByDate[FormatHelper::date($order->getExpectedDelivery(), "Ymd")][] = $order;
        }

        $sort = [
            Status::ORDER_TO_VALIDATE => 1,
            Status::ORDER_PLANNED => 2,
            Status::ORDER_TRANSIT => 3,
        ];

        //generate cards configuration for twig
        $planning = [];
        $period = new DatePeriod($from, new DateInterval("P1D"), $to);
        foreach ($period as $date) {
            $orders = $ordersByDate[$date->format(FormatHelper::DATE_COMPACT)] ?? [];

            $column = [
                "title" => FormatHelper::weekDay($date) . " " . $date->format("d") . " "  . FormatHelper::month($date),
                "orders" => Stream::from($orders)
                    ->sort(fn($a, $b) => $sort[$a->getStatus()->getCode()] - $sort[$b->getStatus()->getCode()])
                    ->values(),
            ];

            $planning[] = $column;
        }

        if($json) {
            return $this->json([
                "planning" => $this->renderView("operation/planning/content.html.twig", [
                    "planning" => $planning,
                ])
            ]);
        } else {
            return $this->render("operation/planning/content.html.twig", [
                "planning" => $planning,
            ]);
        }
    }

}