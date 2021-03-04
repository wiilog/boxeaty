import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newDepositTicketModal = Modal.static(`#modal-new-deposit-ticket`, {
        ajax: AJAX.route(`POST`, `deposit_ticket_new`),
        table: `#table-deposit-tickets`,
    });

    const deleteDepositTicketModal = Modal.static(`#modal-delete-deposit-ticket`, {
        ajax: AJAX.route(`POST`, `deposit_ticket_delete`),
        table: `#table-deposit-tickets`,
    });

    $(`.new-deposit-ticket`).click(() => newDepositTicketModal.open());

    const table = initDatatable(`#table-deposit-tickets`, {
        ajax: AJAX.route(`POST`, `deposit_tickets_api`),
        columns: [
            {data: `creationDate`, title: `Date de création`},
            {data: `kiosk`, title: `Lieu de création`},
            {data: `validityDate`, title: `Date de validité`},
            {data: `number`, title: `Numéro de consigne`},
            {data: `useDate`, title: `Date et heure d'utilisation de la consigne`},
            {data: `client`, title: `Emplacement de la consigne`},
            {data: `state`, title: `Etat`},
            DATATABLE_ACTIONS,
        ],
        order: [[`creationDate`, `desc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `deposit_ticket_edit_template`, {
                    depositTicket: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteDepositTicketModal.open(data),
        }
    });
});
