import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import QrScanner from "qr-scanner";
import Flash from "../flash";

$(document).ready(() => {
    QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

    const orderId = $('[name=order-id]').val();
    $('#modal-delete-order').find('[name=id]').val(orderId);
    const confirmationOrderModal = Modal.static(`#modal-confirmation-order`, {
        submitter: () => {
            confirmationOrderModal.handleSubmit();
        }
    });

    const newOrderModal = Modal.static(`#modal-new-order`, {
        ajax: AJAX.route(`POST`, `order_new`),
        submitter: function () {
            const $depositTicketContainer = $('.deposit-ticket-container');
            const $depositTicket = $depositTicketContainer.find('.deposit-ticket');
            const $totalCost = $depositTicketContainer.find('.total-cost');

            if ($depositTicketContainer.hasClass('d-none')) {
                $depositTicketContainer.removeClass('d-none');
                $depositTicket.attr('required');
                $totalCost.attr('required');
                $('.scan-box').addClass('d-none');
                $('select[name=box]').prop('disabled', true);
            } else {
                newOrderModal.handleSubmit();
                confirmationOrderModal.open(() => {
                    return location.reload();
                });
            }
        }
    });

    const deleteOrderModal = Modal.static(`#modal-delete-order`, {
        ajax: AJAX.route(`POST`, `order_delete`),
        success: () => {
            return location.reload();
        }
    });

    const scanModal = Modal.static(`#modal-scan`);

    $(`.new-order`).click(() => {
        newOrderModal.open();
        $('.scan-box').removeClass('d-none');
        $('.deposit-ticket-container').addClass('d-none');
        $('select[name=box]').prop('disabled', false);
    });

    $(`.delete-order`).click(() => deleteOrderModal.open());

    $(`.scan-box`).click(function () {
        const $modal = $(this).closest('.modal');
        scan(scanModal, $modal.find('select[name=box]'), {
            success: 'La Box a bien été ajoutée',
            warning: 'La Box n\'existe pas'
        });
    });

    $(`.deposit-ticket-scan`).click(function () {
        const $modal = $(this).closest('.modal');
        scan(scanModal, $modal.find('select[name=depositTicket]'), {
            success: 'Le ticket-consigne a bien été ajouté',
            warning: 'Le ticket-consigne n\'existe pas'
        });
    });

    $('select[name=box]').on('change', function () {
        calculateTotalCost($('select[name=box]'));
    });

    $('select[name=depositTicket]').on('change', function () {
        calculateTotalCost($('select[name=depositTicket]'));
    });
});

function scan(scanModal, $select, msg, freeSelect = false) {
    scanModal.open()
    const qrScanner = new QrScanner($('.scan-element')[0], result => {
        if (result) {
            if (freeSelect) {
                let option = new Option(result, result, true, true);
                $select.append(option).trigger('change');
            } else if ($select.find(`option[value='${result}']`).length > 0) {
                let selectedOptions = $select.find(`option:selected`).map(function () {
                    return $(this).val();
                }).toArray();

                selectedOptions.push(result);

                $select.val(selectedOptions).trigger("change");
                Flash.add('success', msg.success);
            } else {
                Flash.add('warning', msg.warning)
            }
            qrScanner.destroy();
            scanModal.close();
        }
    });

    $('#modal-scan').on('hidden.bs.modal', function () {
        qrScanner.destroy();
    });
    qrScanner.start();
}

function calculateTotalCost($select) {
    const $modal = $select.closest('.modal');

    const $boxesSelect = $modal.find('select[name=box] option:selected');
    const $depositTicketsSelect = $modal.find('select[name=depositTicket] option:selected');

    const totalBoxesPrice = $boxesSelect
        .map(function () {
            return Number($(this).data('price'));
        })
        .toArray()
        .reduce((carry, current) => carry + (current || 0), 0);

    const totalDepositTicketsPrice = $depositTicketsSelect
        .map(function () {
            return Number($(this).data('price'));
        })
        .toArray()
        .reduce((carry, current) => carry + (current || 0), 0);

    const totalCost = ((totalBoxesPrice - totalDepositTicketsPrice) || 0);

    const totalCostStr = totalCost
        .toFixed(2)
        .replace('.', ',');


    $modal.find('input[name=totalCost]').val(totalCostStr);
    const $hint = $modal.find('.hint');
    const $costLabel = $modal.find('.cost-label');
    if(totalCost > 0) {
        $costLabel.text('Coût total');
        $hint
            .text('Pensez à demander le versement de la consigne en caisse !')
            .removeClass('d-none');
    } else if(totalCost < 0) {
        $costLabel.text('Dû total');
        $hint
            .text('Pensez à verser le remboursement de la consigne !')
            .removeClass('d-none');
    } else {
        $costLabel.text('Aucun coût');
        $hint.addClass('d-none');
    }
}
