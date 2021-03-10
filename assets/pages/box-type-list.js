import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newBoxTypeModal = Modal.static(`#modal-new-box-type`, {
        ajax: AJAX.route(`POST`, `box_type_new`),
        table: `#table-box-types`,
    });

    $(`.new-box-type`).click(() => newBoxTypeModal.open());

    const table = initDatatable(`#table-box-types`, {
        ajax: AJAX.route(`POST`, `box_types_api`),
        columns: [
            {data: `name`, title: `Type de Box`},
            {data: `active`, title: `Actif`},
            {data: `price`, title: `Prix`},
            {data: `capacity`, title: `Contenance`},
            {data: `shape`, title: `Forme`},
            DATATABLE_ACTIONS
        ],
        order: [[`name`, `asc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `box_type_edit_template`, {
                    boxType: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
