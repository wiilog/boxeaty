import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";
import Select2 from "../select2";

$(document).ready(() => {
    const newClientModal = Modal.static(`#modal-new-client`, {
        ajax: AJAX.route(`POST`, `client_new`),
        table: `#table-clients`,
    });
    Select2.init($(`[name=group], [name=user]`));

    $(`.new-client`).click(() => newClientModal.open());

    const table = initDatatable(`#table-clients`, {
        ajax: {
            url: Routing.generate(`clients_api`),
            method: `POST`,
        },
        columns: [
            {data: `name`, title: `Nom du client`},
            {data: `active`, title: `Actif`},
            {data: `address`, title: `Adresse`},
            {data: `assignedUser`, title: `Utilisateur attribué`},
            DATATABLE_ACTIONS,
        ],
        order: [[`name`, `asc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `client_edit_template`, {
                    client: data.id
                });

                Modal.load(ajax, {table})
            }
        }
    });
});
