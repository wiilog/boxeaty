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
                const ajax = AJAX.route(`POST`, `work_free_day_delete`, {
                    day: data.id
                });

                Modal.load(ajax, {workFreeDayTable})
            },
        }
    });

    $(`#add-work-free-day`).click(function () {
        const $form = $(this).closest(`.inline-form`);

        AJAX.route(`POST`, `work_free_day_add`)
            .json(processForm($form), result => {
                console.log(result);
                if (handleErrors($form, result)) {
                    workFreeDayTable.ajax.reload();
                    clearForm($form);
                }
            });
    });

    $(`button[type="submit"]`).click(() => AJAX
        .route(`POST`, `settings_update`)
        .json(processForm($(`.global-settings`))));
})

