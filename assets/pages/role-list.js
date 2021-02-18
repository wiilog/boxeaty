import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newRoleModal = Modal.static(`#modal-new-role`, {
        ajax: AJAX.route(`POST`, `role_new`),
        table: `#table-roles`,
    });
    const deleteRoleModal = Modal.static(`#modal-delete-role`, AJAX.route(`POST`, `role_delete`));

    $(`.new-role`).click(() => newRoleModal.open());

    const table = initDatatable(`#table-roles`, {
        ajax: AJAX.route(`POST`, `roles_api`),
        columns: [
            {data: `name`, title: `Nom`},
            {data: `active`, title: `Actif`},
            DATATABLE_ACTIONS,
        ],
        order: [[`name`, `asc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `role_edit_template`, {
                    role: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                deleteRoleModal.elem().find(`.not-deletable`)
                deleteRoleModal.open(data);
            },
        }
    });
});
