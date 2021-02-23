import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newClientModal = Modal.static(`#modal-new-client`, {
        ajax: AJAX.route(`POST`, `client_new`),
        table: `#table-clients`,
    });

    $(`.new-client`).click(() => newClientModal.open());

    $(document).on(`change`, `[name="isMultiSite"]`, function () {
        const $isMultiSite = $(this);
        const $multiSite = $isMultiSite.parents(`.modal`).find(`[name="linkedMultiSite"]`);

        if($isMultiSite.is(`:checked`)) {
            $multiSite.val(null).trigger(`change`);
        }

        $multiSite.attr(`disabled`, $isMultiSite.is(`:checked`));
    })

    const table = initDatatable(`#table-clients`, {
        ajax: AJAX.route(`POST`, `clients_api`),
        columns: [
            {data: `name`, title: `Nom du client`},
            {data: `active`, title: `Actif`},
            {data: `address`, title: `Adresse`},
            {data: `contact`, title: `Utilisateur attribué`},
            {data: `group`, title: `Groupe`},
            {data: `multiSite`, title: `Multi-site`},
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
