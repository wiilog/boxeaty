import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newGroupModal = Modal.static(`#modal-new-group`, {
        ajax: AJAX.route(`POST`, `group_new`),
        table: `#table-groups`,
    });

    $(`.new-group`).click(() => newGroupModal.open());

    const table = initDatatable(`#table-groups`, {
        ajax: AJAX.route(`POST`, `groups_api`),
        columns: [
            {data: `name`, title: `Nom du groupe`},
            {data: `active`, title: `Actif`},
            DATATABLE_ACTIONS
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `group_edit_template`, {
                    group: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
