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

    $(`.new-movement`).click(() => {
        const now = new Date();
        newMovementModal.open({
            date: `${now.getFullYear()}-${leadingZero(now.getMonth() + 1)}-${leadingZero(now.getDate())}T${leadingZero(now.getHours())}:${leadingZero(now.getMinutes())}`
        })
    });

    const table = initDatatable(`#table-movements`, {
        ajax: AJAX.route(`POST`, `tracking_movements_api`),
        columns: [
            {data: `date`, title: `Date`},
            {data: `location`, title: `Emplacement`},
            {data: `box`, title: `Numéro box`},
            {data: `quality`, title: `Qualité`},
            {data: `state`, title: `Etat`},
            {data: `client`, title: `Client`},
            {data: `user`, title: `Utilisateur`},
            DATATABLE_ACTIONS,
        ],
        order: [[`date`, `desc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `tracking_movement_edit_template`, {
                    movement: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteMovementModal.open(data),
        }
    });
});

function leadingZero(number) {
    return (number < 10 ? '0' : '') + number;
}
