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
        success: () => {
            const $modal = $(`#modal-new-client`);

            $modal.find(`.client-self-name`).text(`Client actuel`);
            $modal.find(`select[name="depositTicketsClients"]`)
                .val(0)
                .trigger(`change`);
        }
    });

    $(`.new-client`).click(() => newClientModal.open());

    $(`#modal-new-client input[name="name"]`).keyup(function() {
        let $option = $(`#modal-new-client`).find(`.client-self-name`);
        $option.text($(this).val());
        Select2.init($option.parent());
    });

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
            {data: `contact`, title: `Contact attribué`},
            {data: `group`, title: `Groupe`},
            {data: `multiSite`, title: `Multi-site`},
            {data: `linkedMultiSite`, title: `Multi-site lié`},
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
