import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newBoxTypeModal = Modal.static(`#modal-new-box-type`, {
        ajax: AJAX.route(`POST`, `box_type_new`),
        table: `#table-box-types`,
    });

    $(`.new-box-type`).click(() => newBoxTypeModal.open());

    const table = initDatatable(`#table-box-types`, {
        ajax: AJAX.route(`POST`, `box_types_api`),
        columns: [
            {data: `name`, title: `Type de box`},
            {data: `price`, title: `Prix`},
            {data: `active`, title: `Actif`},
        ],
        order: [[`name`, `asc`]],
        listeners: {
            action: data => {
                const ajax = AJAX.route(`POST`, `box_type_edit_template`, {
                    boxType: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
