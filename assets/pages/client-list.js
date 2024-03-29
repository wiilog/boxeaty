import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Select2 from "../select2";

$(document).ready(() => {
    const newClientModal = Modal.static(`#modal-new-client`, {
        ajax: AJAX.route(`POST`, `client_new`),
        table: `#table-clients`,
        afterOpen: (modal) => {
            const $modal = modal.elem();
            const $select = $modal.find('[name="depositTicketsClients"]');
            $select.val('0').trigger('change');
        },
        success: () => {
            const $modal = $(`#modal-new-client`);

            $modal.find(`.client-self-name`).text(`Client actuel`);
            $modal.find(`select[name="depositTicketsClients"]`)
                .val(0)
                .trigger(`change`);
        }
    });

    $(`.new-client`).click(() => newClientModal.open());

    $(`#modal-new-client input[name="name"]`).on('input', function() {
        let $option = $(`#modal-new-client`).find(`.client-self-name`);
        $option.text($(this).val());

        const $select = $option.parent();
        $select.data(`s2`, `client`);
        Select2.init($select);
    });

    $(document).on(`change`, `[name="isMultiSite"]`, function () {
        const $isMultiSite = $(this);
        const $multiSite = $isMultiSite.parents(`.modal`).find(`[name="linkedMultiSite"]`);

        if($isMultiSite.is(`:checked`)) {
            $multiSite.val(null).trigger(`change`);
        }

        $multiSite.attr(`disabled`, $isMultiSite.is(`:checked`));
    });

    const table = initDatatable(`#table-clients`, {
        ajax: AJAX.route(`POST`, `clients_api`),
        columns: [
            {data: `name`, title: `Nom Client`},
            {data: `active`, title: `Actif`},
            {data: `address`, title: `Adresse`},
            {data: `contact`, title: `Contact attribué`},
            {data: `group`, title: `Groupe`},
            {data: `linkedMultiSite`, title: `Multi-site lié`},
            {data: `isMultiSite`, title: `Multi-site`},
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `client_edit_template`, {
                    client: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                const ajax = AJAX.route(`POST`, `client_delete_template`, {
                    client: data.id
                });

                Modal.load(ajax, {table})
            },
            action: data => {
                window.location.href = Routing.generate(`client_show`, {
                    client: data.id
                });
            },
        }
    });
});
