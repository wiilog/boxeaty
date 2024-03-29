import {$document} from '../app';
import './counter-order';

import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$document.ready(() => {
    const deleteOrderModal = Modal.static(`#modal-delete-order`, {
        ajax: AJAX.route(`POST`, `counter_order_delete`),
    });

    initDatatable(`#table-orders`, {
        ajax: AJAX.route(`POST`, `counter_orders_api`),
        columns: [
            {data: `boxes`, title: `Identifiant Box`},
            {data: `depositTickets`, title: `Ticket(s) consigne`},
            {data: `location`, title: `Emplacement`},
            {data: `boxPrice`, title: `Coût des Box`},
            {data: `depositTicketPrice`, title: `Valeur des tickets-consignes`},
            {data: `totalCost`, title: `Consigne à régler`},
            {data: `user`, title: `Utilisateur`},
            {data: `client`, title: `Client`},
            {data: `date`, title: `Date et heure de création`},
            DATATABLE_ACTIONS
        ],
        listeners: {
            delete: data => deleteOrderModal.open(data),
        }
    });
});
