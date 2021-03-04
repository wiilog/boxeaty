import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import QrScanner from "qr-scanner";
import Flash from "../flash";

const boxPrices = {};
const depositTicketPrices = {};

$(document).ready(() => {
    QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

    const orderId = $('[name=order-id]').val();
    $('#modal-delete-order').find('[name=id]').val(orderId);
    const confirmationOrderModal = Modal.static(`#modal-confirmation-order`, {
        submitter: () => {
            confirmationOrderModal.handleSubmit();
            window.location.reload();
        },
    });

    const newOrderModal = Modal.static(`#modal-new-order`, {
        ajax: AJAX.route(`POST`, `order_new`),
        submitter: function() {
            const $depositTicketContainer = $('.deposit-ticket-container');
            const $depositTicket = $depositTicketContainer.find('.deposit-ticket');
            const $totalCost = $depositTicketContainer.find('.total-cost');

            if($depositTicketContainer.hasClass('d-none')) {
                $depositTicketContainer.removeClass('d-none');
                $depositTicket.attr('required');
                $totalCost.attr('required');
                $('.scan-box').addClass('d-none');
                $('select[name=box]').prop('disabled', true);
            } else {
                newOrderModal.handleSubmit();
                confirmationOrderModal.open();
            }
        }
    });

    const deleteOrderModal = Modal.static(`#modal-delete-order`, {
        ajax: AJAX.route(`POST`, `order_delete`),
        success: () => window.location.reload(),
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
        scan(scanModal, $modal.find('select[name=box]'), `ajax_select_available_boxes`, {
            success: 'La Box a bien été ajoutée',
            warning: 'La Box n\'existe pas'
        });
    });

    $(`.deposit-ticket-scan`).click(function() {
        const $modal = $(this).closest('.modal');
        scan(scanModal, $modal.find('select[name=depositTicket]'), `ajax_select_deposit_tickets`, {
            success: 'Le ticket-consigne a bien été ajouté',
            warning: `Le ticket-consigne n'existe pas, a déjà été utilisé ou n'est plus valide`
        });
    });

    $('select[name=box]').on('change', function() {
        calculateTotalCost($('select[name=box]'));
    });

    $('select[name=depositTicket]').on('change', function() {
        calculateTotalCost($('select[name=depositTicket]'));
    });
});

function scan(scanModal, $select, route, msg) {
    scanModal.open()
    const qrScanner = new QrScanner($('.scan-element')[0], result => {
        if(result) {
            AJAX.url(`GET`, Routing.generate(route) + `?term=${result}`).json(results => {
                const idk = results.results.find(r => r.text === result);

                if(idk) {
                    if(route === `ajax_select_available_boxes`) {
                        boxPrices[idk.text] = idk.price;
                    } else {
                        depositTicketPrices[idk.text] = idk.price;
                    }

                    let selectedOptions = $select.find(`option:selected`).map(function() {
                        return $(this).val();
                    }).toArray();

                    if($select.find(`option[value='${result}']`).length === 0) {
                        let option = new Option(result, result, true, true);
                        $select.append(option);
                    }

                    selectedOptions.push(result);
                    $select.val(selectedOptions).trigger("change");
                    Flash.add('success', msg.success);
                } else {
                    Flash.add('warning', msg.warning)
                }

                scanModal.close();
            });

            qrScanner.destroy();
        }
    });

    $('#modal-scan').on('hidden.bs.modal', function() {
        qrScanner.destroy();
    });
    qrScanner.start();
}

function calculateTotalCost($select) {
    const $modal = $select.closest('.modal');

    const $boxesSelect = $modal.find('select[name=box]').select2('data');
    const $depositTicketsSelect = $modal.find('select[name=depositTicket]').select2('data');

    const totalBoxesPrice = $boxesSelect
        .map(function(item) {
            return Number(item.price ? item.price : boxPrices[item.text]);
        })
        .reduce((carry, current) => carry + (current || 0), 0);

    const totalDepositTicketsPrice = $depositTicketsSelect
        .map(function(item) {
            return Number(item.price ? item.price : depositTicketPrices[item.text]);
        })
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
