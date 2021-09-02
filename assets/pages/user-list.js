import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";
import {$document} from "../app";

$(document).ready(() => {
    const newUserModal = Modal.static(`#modal-new-user`, {
        ajax: AJAX.route(`POST`, `user_new`),
        table: `#table-users`,
    });

    $(`.new-user`).click(() => newUserModal.open());

    $document.on(`change`, `input[name=deliverer]`, function() {
        const $field = $(this);
        const $method = $field.closest(`.modal`).find(`.delivery-method`);

        $method.toggleClass(`d-none`, !$field.is(':checked'));
        $method.find('select').prop('required', $(this).is(':checked'));
    });


    $(document).on(`click`, `button.change-password`, function() {
        $(this).parents(`.modal`).find(`div.change-password`).slideToggle();
    });

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
            edit: (data) => {
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
        onGroupsChange($(this));
    });
});

function onGroupsChange($groups) {
    const $modal = $groups.closest('.modal');
    const $clients = $modal.find(`select[name="clients"]`);
    const groups = $groups.val();

    $clients.prop(`disabled`, groups.length > 1);
    $clients.val(null).trigger(`change`);
}
