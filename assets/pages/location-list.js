import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$(document).ready(() => {
    const newLocationModal = Modal.static(`#modal-new-location`, {
        ajax: AJAX.route(`POST`, `location_new`),
        table: `#table-locations`,
        afterOpen: (modal) => {
            toggleCapacityInput(modal.elem().find('[name="type"]'));
        }
    });

    const emptyLocationModal = Modal.static(`#modal-empty-location`, {
        ajax: AJAX.route(`POST`, `api_empty_kiosk`),
        table: `#table-locations`,
    });

    const table = initDatatable(`#table-locations`, {
        ajax: AJAX.route(`POST`, `locations_api`),
        columns: [
            {data: `type`, title: `Type`, orderable: false},
            {data: `name`, title: `Nom de l'emplacement`},
            {data: `client_name`, title: `Nom du client`},
            {data: `active`, title: `Actif`},
            {data: `description`, title: `Description`},
            {data: `boxes`, title: `Nombre de Box`, orderable: false},
            {data: `capacity`, title: `Capacité`},
            DATATABLE_ACTIONS,
        ],
        listeners: {
            edit: data => {
                const ajax = AJAX.route(`POST`, `location_edit_template`, {
                    location: data.id
                });

                Modal.load(ajax, {table});
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

    fireTypeChangeEvent($('#modal-new-location').find('input[name="type"]'));
    $(document)
        .arrive('#modal-edit-location .location-type', function() {
            fireTypeChangeEvent($(this).find('input[name="type"]'));
        });
});

function fireTypeChangeEvent($type) {
    $type.on('change', function () {
        const $type = $(this);
        toggleCapacityInput($type);
    })
}

function toggleCapacityInput($typeRadio) {
    const $checkedRadio = $typeRadio.filter(`:checked`);
    const $modal = $typeRadio.closest('.modal');
    const $kioskCapacity = $modal.find('.kiosk-capacity');

    if (parseInt($checkedRadio.val()) === 1) {
        $kioskCapacity.removeClass('d-none');
        $kioskCapacity.find('input').prop('required', true);
    } else {
        $kioskCapacity.addClass('d-none');
        $kioskCapacity.find('input').val('');
        $kioskCapacity.find('input').prop('required', false);
    }
}
