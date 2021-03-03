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

    const newOrderModal = Modal.static(`#modal-new-order`, {
        ajax: AJAX.route(`POST`, `order_new`),
        submitter: function () {
            const $depositTicketContainer = $('.deposit-ticket-container');
            const $depositTicket = $depositTicketContainer.find('.deposit-ticket');
            const $totalCost = $depositTicketContainer.find('.total-cost');

            if($depositTicketContainer.hasClass('d-none')) {
                if($(`[name=box] option:selected`).length < 1) {
                    Flash.add('warning', `Merci d'ajouter au moins une Box`);
                } else {
                    $depositTicketContainer.removeClass('d-none');
                    $depositTicket.attr('required');
                    $totalCost.attr('required');
                    $('.scan-box').addClass('d-none');
                    $('select[name=box]').prop('disabled', true);
                }
            } else {
                newOrderModal.handleSubmit();
                return location.reload();
            }
        }
    });

    const deleteOrderModal = Modal.static(`#modal-delete-order`, {
        ajax: AJAX.route(`POST`, `order_delete`),
    });

    const scanModal = Modal.static(`#modal-scan`);

    $(`.new-order`).click(() => {
        newOrderModal.open();
        $('.scan-box').removeClass('d-none');
        $('.deposit-ticket-container').addClass('d-none');
        $('select[name=box]').prop('disabled', false);
    });
    $(`.delete-order`).click(() => deleteOrderModal.open());

    $(`.scan-box`).click(function() {
        const $modal = $(this).closest('.modal');
        scan(scanModal, $modal.find('select[name=box]'), {
            success: 'La Box a bien été ajoutée',
            warning: 'La Box n\'existe pas'
        });
    });

    $(`.deposit-ticket-scan`).click(function() {
        const $modal = $(this).closest('.modal');
        scan(scanModal, $modal.find('select[name=depositTicket]'), {
            success: 'Le ticket-consigne a bien été ajouté',
            warning: 'Le ticket-consigne n\'existe pas'
        });
    });
});

function scan(scanModal, $select, msg, freeSelect = false) {
    scanModal.open()
    const qrScanner = new QrScanner($('.scan-element')[0], result => {
        if(result) {
            if(freeSelect) {
                let option = new Option(result, result, true, true);
                $select.append(option).trigger('change');
            }
            else if($select.find(`option[value='${result}']`).length > 0) {
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
