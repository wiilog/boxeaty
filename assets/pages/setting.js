import {$document} from '../app';

import $ from "jquery";
import Modal, {clearForm, handleErrors, processForm} from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";

$document.ready(() => {
    const workFreeDayTable = initDatatable(`#table-work-free-days`, {
        ajax: AJAX.route(`POST`, `work_free_day_api`),
        columns: [
            {data: `day`, title: `Jour ferié`},
            DATATABLE_ACTIONS,
        ],
        listeners: {
            delete: data => {
                AJAX.route(`POST`, `work_free_day_delete`, {
                    day: data.id
                }).json((result) => {
                    if(result.success) {
                        workFreeDayTable.ajax.reload();
                    }
                });
            },
        }
    });

    $(`#add-work-free-day`).click(function () {
        const $form = $(this).closest(`.inline-form`);

        if (processForm($form)) {
            AJAX.route(`POST`, `work_free_day_add`)
                .json(processForm($form), result => {
                    if (handleErrors($form, result)) {
                        workFreeDayTable.ajax.reload();
                        clearForm($form);
                    }
                });
        }
    });

    $(`#add-transport-mode`).click(function () {
        const $form = $(this).closest(`.inline-form`);
        if (processForm($form)) {
            AJAX.route(`POST`, `delivery_mode_add`)
                .json(processForm($form), result => {
                    if (handleErrors($form, result)) {
                        deliveryMethodTable.ajax.reload();
                        $("input[name=nameDeliveryMethode]").val('');
                    }
                });
        }
    });

    const deliveryMethodTable = initDatatable(`#table-delivery-methode`, {
        ajax: AJAX.route(`POST`, `delivery_method_api`),
        columns: [
            {data: `name`, title: `Type de mobilité`},
            {data:`icon`, title: `Icone`},
            DATATABLE_ACTIONS,
        ],
        listeners: {
            delete: data => {
                AJAX.route(`POST`, `delivery_methode_delete`, {
                    deliveryMethod: data.id
                }).json((result) => {
                    if(result.success) {
                        deliveryMethodTable.ajax.reload();
                    }
                });
            },
        }
    });

    $(`button[type="submit"]`).click(() => AJAX
        .route(`POST`, `settings_update`)
        .json(processForm($(`.global-settings`)))
    );
});
