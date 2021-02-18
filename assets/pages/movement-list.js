import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newMovementModal = Modal.static(`#modal-new-movement`, {
        ajax: AJAX.route(`POST`, `tracking_movement_new`),
        table: `#table-movements`,
    });

    const deleteMovementModal = Modal.static(`#modal-delete-movement`, {
        ajax: AJAX.route(`POST`, `tracking_movement_delete`),
        table: `#table-movements`,
    });

    $(`.new-movement`).click(() => newMovementModal.open());

    const table = initDatatable(`#table-movements`, {
        ajax: AJAX.route(`POST`, `tracking_movements_api`),
        columns: [
            {data: `date`, title: `Date`},
            {data: `box`, title: `Numéro box`},
            {data: `quality`, title: `Qualité`},
            {data: `state`, title: `Etat`},
            {data: `client`, title: `Client`},
            DATATABLE_ACTIONS,
        ],
        order: [[`date`, `asc`]],
        listeners: {
            action: data => {
                const ajax = AJAX.route(`POST`, `tracking_movement_edit_template`, {
                    movement: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteMovementModal.open(data),
        }
    });
});
