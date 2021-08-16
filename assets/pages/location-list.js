import {$document} from '../app';
import "../styles/pages/location.scss";
import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$document.ready(() => {
    const newLocationModal = Modal.static(`#modal-new-location`, {
        ajax: AJAX.route(`POST`, `location_new`),
        table: `#table-locations`,
        afterOpen: (modal) => {
            toggleCapacityInput(modal.elem().find('[name="kiosk"]'));
        }
    });

    const emptyLocationModal = Modal.static(`#modal-empty-location`, {
        ajax: AJAX.route(`POST`, `api_kiosk_empty_kiosk`),
        table: `#table-locations`,
    });

    $(`.new-location`).click(() => newLocationModal.open());

    const table = initDatatable(`#table-locations`, {
        ajax: AJAX.route(`POST`, `locations_api`),
        columns: [
            {data: `kiosk`, title: `Type`},
            {data: `name`, title: `Nom de l'emplacement`},
            {data: `depository`, title: `Dépôt`},
            {data: `client_name`, title: `Nom du client`},
            {data: `active`, title: `Actif`},
            {data: `description`, title: `Description`},
            {data: `capacity`, title: `Capacité`},
            {data: `location_type`, title: `Type d'emplacement`},
            {data: `container_amount`, title: `Nombre de contenants`, orderable: false},
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
});

$document.ready(() => fireTypeChangeEvent($('#modal-new-location').find('input[name="kiosk"]')))
    .arrive(`#modal-edit-location .location-type`, function() {
        fireTypeChangeEvent($(this).find('input[name="kiosk"]'));
        toggleCapacityInput($(this).find('input[name="kiosk"]'));
    });

function fireTypeChangeEvent($type) {
    $type.on('change', function() {
        toggleCapacityInput($(this));
    })
}

function toggleCapacityInput($typeRadio) {
    const $checkedRadio = $typeRadio.filter(`:checked`);
    const $modal = $typeRadio.closest(`.modal`);
    const $kioskFields = $modal.find(`.kiosk-fields`);
    const $locationFields = $modal.find(`.location-fields`);

    if(parseInt($checkedRadio.val()) === 1) {
        toggleInputsIn($kioskFields, true);
        toggleInputsIn($locationFields, false);
    } else {
        toggleInputsIn($kioskFields, false);
        toggleInputsIn($locationFields, true);
    }
}

function toggleInputsIn($container, show) {
    if(show) {
        $container.removeClass(`d-none`);
        $container.find(`input:not(.no-clear), select`).val(``);
        $container.find(`input[data-required], select[data-required]`).each(function() {
            $(this).prop(`required`, true);
        });
    } else {
        $container.addClass(`d-none`);
        $container.find(`input[required], select[required]`).each(function() {
            const $input = $(this);
            $input.data(`required`, $input.is(`[required]`));
            $input.prop(`required`, false);
        });
    }
}
