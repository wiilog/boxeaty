import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Modal from "../modal";

import "../styles/pages/client-show.scss";
import {StringHelper} from "../util";

$(document).ready(() => {
    getCratePattern();
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
            success: () => {
                window.location.href = Routing.generate(`clients_list`);
            }
        })
    });

    const addCratePatternLineModal = Modal.static(`#modal-add-crate-pattern-line`, {
        ajax: AJAX.route(`POST`, `add_crate_pattern_line`),
        success: () => {
            getCratePattern();
            getBoxRecurrence();
        }
    });

    $(`.add-crate-pattern-line`).click(() => addCratePatternLineModal.open());

    $(document).arrive(`.delete-crate-pattern-line`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `crate_pattern_line_delete_template`, {
                cratePatternLine: $(this).data('id'),
            });

            Modal.load(ajax, {
                success: () => {
                    getCratePattern();
                    getBoxRecurrence();
                }
            });
        });
    });

    $(document).arrive(`.edit-crate-pattern-line`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `crate_pattern_line_edit_template`, {
                cratePatternLine: $(this).data('id'),
            });

            Modal.load(ajax, {
                success: () => {
                    getCratePattern();
                    getBoxRecurrence();
                }
            });
        });
    });

    const addOrderRecurrence = Modal.static(`#modal-add-order-recurrence`, {
        ajax: AJAX.route(`POST`, `add_order_recurrence`),
        success: () => {
            getCratePattern();
            getBoxRecurrence();
        }
    });

    $(document).arrive(`.add-order-recurrence`, function () {
        $(this).click(() => addOrderRecurrence.open());
    });

    $(document).arrive(`.edit-order-recurrence`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `order_recurrence_edit_template`, {
                orderRecurrence: $(this).data('id'),
            });

            Modal.load(ajax, {
                success: () => {
                    getCratePattern();
                    getBoxRecurrence();
                }
            });
        });
    });

    $(document).arrive(`.delete-recurrence`, function () {
        $(this).click(() => {
            AJAX.route(`POST`, `order_recurrence_delete`, {
                orderRecurrence: $(this).data('id'),
            }).json()
                .then((data) => {
                    if (data.success) {
                        getBoxRecurrence();
                    }
                });
        })
    });
});

function getCratePattern() {
    AJAX.route(`GET`, `crate_pattern_lines_api`, {id: $('#client-id').val()})
        .json()
        .then((response) => {
            $('.box-type-card-wrapper').empty().append(response.template);
            $('.total-crate-type-price').text(`à ${response.totalCrateTypePrice}`);
        });
}

function getBoxRecurrence() {
    AJAX.route(`GET`, `order_recurrence_api`, {id: $('#client-id').val()})
        .json()
        .then((response) => {
            let formattedPrice = ``;
            if(response.orderRecurrencePrice) {
                formattedPrice = StringHelper.formatPrice(response.orderRecurrencePrice) + `HT/mois`
            }

            $('.order-recurrence-wrapper').empty().append(response.template);
            $('.order-recurrence-price').text(`${formattedPrice} environ`);
        });
}
