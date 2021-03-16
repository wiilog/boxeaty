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

    $(`.new-user`).click(() => newUserModal.open());

    const table = initDatatable(`#table-users`, {
        ajax: AJAX.route(`POST`, `users_api`),
        columns: [
            {data: `email`, title: `Email`},
            {data: `username`, title: `Nom d'utilisateur`},
            {data: `role`, title: `Rôle`},
            {data: `lastLogin`, title: `Dernière connexion`},
            {data: `status`, title: `Statut`},
            DATATABLE_ACTIONS,
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `user_edit_template`, {
                    user: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                const ajax = AJAX.route(`POST`, `user_delete_template`, {
                    user: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });

    $(document).on(`change`, `select[name="groups"]`, function() {
        const $clients = $(`select[name="clients"]`);
        const groups = $(this).val();

        $clients.attr(`disabled`, groups.length > 1);
        if(groups.length > 1) {
            $clients.val(null).trigger(`change`);
        }
    });
});
