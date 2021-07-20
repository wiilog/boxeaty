import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Modal from "../modal";

import "../styles/pages/client-show.scss";

$(document).ready(() => {
    getBoxTypes();
    getBoxRecurrence();

    $(`.edit-client`).click(() => {
        const ajax = AJAX.route(`POST`, `client_edit_template`, {
            client: $('#client-id').val()
        });

        Modal.load(ajax, {
            success: () => window.location.reload()
        });
    });

    $(`.delete-client`).click(() => {
        const ajax = AJAX.route(`POST`, `client_delete_template`, {
            client: $('#client-id').val()
        });
        Modal.load(ajax, {
            success : () =>{
                window.location.href = Routing.generate(`clients_list`);
            }
        })
    });

    const addClientBoxTypeModal = Modal.static(`#modal-add-client-box-type`, {
        ajax: AJAX.route(`POST`, `add_client_box_type`),
        success: () => {
            getBoxTypes();
            getBoxRecurrence();
        }
    });

    $(`.add-client-box-type`).click(() => addClientBoxTypeModal.open());

    $(document).arrive(`.delete-client-box-type`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `client_box_type_delete_template`, {
                clientBoxType: $(this).data('id'),
            });

            Modal.load(ajax, {
                success : () =>{
                    getBoxTypes();
                    getBoxRecurrence();
                }
            });
        });
    });

    $(document).arrive(`.edit-client-box-type`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `client_box_type_edit_template`, {
                clientBoxType: $(this).data('id'),
            });

            Modal.load(ajax, {
                success : () =>{
                    getBoxTypes();
                    getBoxRecurrence();
                }
            });
        });
    });

    const addOrderRecurrence = Modal.static(`#modal-add-order-recurrence`, {
        ajax: AJAX.route(`POST`, `add_order_recurrence`),
        success: () => {
            getBoxTypes();
            getBoxRecurrence();
        }
    });

    $(document).arrive(`.add-order-ocurrence`, function () {
        $(this).click(() => addOrderRecurrence.open());
    });

    $(document).arrive(`.edit-order-ocurrence`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `order_recurrence_edit_template`, {
                orderRecurrence: $(this).data('id'),
            });

            Modal.load(ajax, {
                success : () =>{
                    getBoxTypes();
                    getBoxRecurrence();
                }
            });
        });
    });
});

function getBoxTypes() {
    AJAX.route(`GET`, `client_box_types_api`, {id: $('#client-id').val()})
        .json()
        .then((response) => {
            $('.box-type-card-wrapper').empty().append(response.template);
            $('.total-crate-type-price').text(`à ${response.totalCrateTypePrice} €`);
        });
}

function getBoxRecurrence() {
    AJAX.route(`GET`, `order_recurrence_api`, {id: $('#client-id').val()})
        .json()
        .then((response) => {
            $('.order-recurrence-wrapper').empty().append(response.template);
            $('.order-recurrence-price').text(`${response.orderRecurrencePrice} € HT/mois`);
        });
}
