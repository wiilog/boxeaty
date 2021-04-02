import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newBoxModal = Modal.static(`#modal-new-box`, {
        ajax: AJAX.route(`POST`, `box_new`),
        table: `#table-boxes`,
    });

    $(`.new-box`).click(() => newBoxModal.open());

    const table = initDatatable(`#table-boxes`, {
        ajax: AJAX.route(`POST`, `boxes_api`),
        columns: [
            {data: `id`, class: `d-none`},
            {data: `number`, title: `Numéro Box`},
            {data: `location`, title: `Emplacement`},
            {data: `state`, title: `Etat`},
            {data: `quality`, title: `Qualité`},
            {data: `owner`, title: `Propriété`},
            {data: `type`, title: `Type`},
            DATATABLE_ACTIONS
        ],
        listeners: {
            action: data => {
                window.location.href = Routing.generate(`box_show`, {
                    box: data.id
                });
            },
            edit: data => {
                const ajax = AJAX.route(`POST`, `box_edit_template`, {
                    box: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                const ajax = AJAX.route(`POST`, `box_delete_template`, {
                    box: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
