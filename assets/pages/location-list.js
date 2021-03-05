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

    const deleteLocationModal = Modal.static(`#modal-delete-location`, {
        ajax: AJAX.route(`POST`, `location_delete`),
        table: `#table-locations`,
    });

    const emptyLocationModal = Modal.static(`#modal-empty-location`, {
        ajax: AJAX.route(`POST`, `api_empty_kiosk`),
        table: `#table-locations`,
    });

    const table = initDatatable(`#table-locations`, {
        ajax: AJAX.route(`POST`, `locations_api`),
        columns: [
            {data: `type`, title: `Type`},
            {data: `name`, title: `Nom de l'emplacement`},
            {data: `active`, title: `Actif`},
            {data: `description`, title: `Description`},
            {data: `boxes`, title: `Nombre de Box`},
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
            delete: data => {
                const ajax = AJAX.route(`POST`, `location_delete_template`, {
                    location: data.id
                });

                Modal.load(ajax, {table})
            },
            empty: data => emptyLocationModal.open(data),
        }
    });

    $(`.new-location`).click(() => newLocationModal.open());
});
