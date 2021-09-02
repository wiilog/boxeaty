import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newDepositoryModal = Modal.static(`#modal-new-depository`, {
        ajax: AJAX.route(`POST`, `depository_new`),
        table: `#table-depositories`,
    });

    $(`.new-depository`).click(() => newDepositoryModal.open());

    const table = initDatatable(`#table-depositories`, {
        ajax: AJAX.route(`POST`, `depositories_api`),
        columns: [
            {data: `name`, title: `Nom du dépôt`},
            {data: `active`, title: `Statut`},
            {data: `description`, title: `Description`},
            DATATABLE_ACTIONS
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `depository_edit_template`, {
                    depository: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => {
                const ajax = AJAX.route(`POST`, `depository_delete_template`, {
                    depository: data.id
                });

                Modal.load(ajax, {table})
            },
        }
    });
});
