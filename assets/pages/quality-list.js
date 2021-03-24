import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newQualityModal = Modal.static(`#modal-new-quality`, {
        ajax: AJAX.route(`POST`, `quality_new`),
        table: `#table-qualities`,
    });

    $(`.new-quality`).click(() => newQualityModal.open());

    const table = initDatatable(`#table-qualities`, {
        ajax: AJAX.route(`POST`, `qualities_api`),
        columns: [
            {data: `name`, title: `Nom`},
            DATATABLE_ACTIONS,
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `quality_edit_template`, {
                    quality: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                const ajax = AJAX.route(`POST`, `quality_delete_template`, {
                    quality: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
