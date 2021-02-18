import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newLocationModal = Modal.static(`#modal-new-location`, {
        ajax: AJAX.route(`POST`, `location_new`),
        table: `#table-locations`,
    });
    const deleteLocationModal = Modal.static(`#modal-delete-location`, AJAX.route(`POST`, `location_delete`));

    const table = initDatatable(`#table-locations`, {
        ajax: AJAX.route(`POST`, `locations_api`),
        columns: [
            {data: `name`, title: `Nom de l'emplacement`},
            {data: `active`, title: `Statut`},
            {data: `description`, title: `Description`},
            DATATABLE_ACTIONS,
        ],
        order: [[`name`, `asc`]],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `location_edit_template`, {
                    location: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteLocationModal.open(data),
        }
    });

    $(`.new-location`).click(() => newLocationModal.open());
    $(`.filter`).click(() => table.ajax.reload());
});
