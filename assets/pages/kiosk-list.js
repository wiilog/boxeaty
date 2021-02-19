import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newKioskModal = Modal.static(`#modal-new-kiosk`, {
        ajax: AJAX.route(`POST`, `kiosk_new`),
        table: `#table-kiosks`,
    });

    $(`.new-kiosk`).click(() => newKioskModal.open());

    const table = initDatatable(`#table-kiosks`, {
        ajax: AJAX.route(`POST`, `kiosks_api`),
        columns: [
            {data: `name`, title: `Nom de la borne`},
            {data: `client`, title: `Client`},
        ],
        order: [[`name`, `asc`]],
        listeners: {
            action: data => {
                const ajax = AJAX.route(`POST`, `kiosk_edit_template`, {
                    kiosk: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
