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

    $(`.new-location`).click(() => newLocationModal.open());

    const table = initDatatable(`#table-locations`, {
        ajax: {
            url: Routing.generate(`locations_api`),
            method: `POST`,
        },
        columns: [
            {data: `name`, title: `Nom de l'emplacement`},
            {data: `active`, title: `Statut`},
            {data: `description`, title: `Description`},
            DATATABLE_ACTIONS,
        ],
        order: [[`name`, `asc`]],
        listeners: {
            action: data => {
                alert(`You double clicked on row ${data.id}`);
            },
            edit: data => {
                const ajax = AJAX.route(`POST`, `location_edit_template`, {
                    location: data.id
                });

                Modal.load(ajax, {table})
            },
            delete: data => deleteLocationModal.open(data),
        }
    });
});
