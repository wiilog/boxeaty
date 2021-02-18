import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newMovementModal = Modal.static(`#modal-new-movement`, {
        ajax: AJAX.route(`POST`, `tracking_movement_new`),
        table: `#table-movement`,
    });

    $(`.new-movement`).click(() => newMovementModal.open());

    const table = initDatatable(`#table-movement`, {
        ajax: {
            url: Routing.generate(`tracking_movements_api`),
            method: `POST`,
        },
        columns: [
            {data: `date`, title: `Date`},
            {data: `box`, title: `Numéro box`},
            {data: `quality`, title: `Qualité`},
            {data: `state`, title: `Etat`},
            {data: `client`, title: `Client`},
        ],
        order: [[`name`, `asc`]],
        listeners: {
            action: data => {
                const ajax = AJAX.route(`POST`, `tracking_movement_edit_template`, {
                    movement: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
