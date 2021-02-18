import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newGroupModal = Modal.static(`#modal-new-group`, {
        ajax: AJAX.route(`POST`, `group_new`),
        table: `#table-groups`,
    });

    $(`.new-group`).click(() => newGroupModal.open());

    const table = initDatatable(`#table-groups`, {
        ajax: {
            url: Routing.generate(`groups_api`),
            method: `POST`,
        },
        columns: [
            {data: `name`, title: `Nom du groupe`},
            {data: `establishment`, title: `Nom de l'établissement`},
            {data: `active`, title: `Actif`},
        ],
        order: [[`name`, `asc`]],
        listeners: {
            action: data => {
                const ajax = AJAX.route(`POST`, `group_edit_template`, {
                    group: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
