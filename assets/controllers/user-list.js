import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newUserModal = Modal.static(`#modal-new-user`, {
        ajax: AJAX.route(`POST`, `user_new`),
        table: `#table-users`,
    });
    const deleteUserModal = Modal.static(`#modal-delete-user`, AJAX.route(`POST`, `user_delete`));

    $(`.new-user`).click(() => newUserModal.open());

    const table = initDatatable(`#table-users`, {
        ajax: {
            url: Routing.generate(`users_api`),
            method: `POST`,
        },
        columns: [
            {data: `email`, title: `Email`},
            {data: `username`, title: `Nom d'utilisateur`},
            {data: `role`, title: `Rôle`},
            {data: `lastLogin`, title: `Dernière connexion`},
            DATATABLE_ACTIONS,
        ],
        order: [[`email`, `asc`]],
        listeners: {
            action: data => {
                alert(`You double clicked on row ${data.id}`);
            },
            edit: data => {
                const ajax = AJAX.route(`POST`, `user_edit_template`, {
                    user: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteUserModal.open(data),
        }
    });
});
