import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const deleteUserModal = Modal.init(`#modal-delete-user`, AJAX.for(`POST`, `user_delete`));

    initDatatable(`#table-users`, {
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
            onAction: (data) => {
                alert(`You double clicked on row ${data.id}`);
            },
            onDelete: (data) => deleteUserModal.open(data),
        }
    });
});
