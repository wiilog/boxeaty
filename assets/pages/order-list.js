import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, initDatatable} from "../datatable";
import Scan from "../scan";

const boxPrices = {};
const depositTicketPrices = {};

$(document).ready(() => {

    const orderId = $('[name=order-id]').val();
    $('#modal-delete-order').find('[name=id]').val(orderId);

    const deleteOrderModal = Modal.static(`#modal-delete-order`, {
        ajax: AJAX.route(`POST`, `order_delete`),
        success: () => window.location.reload(),
    });

    const table = initDatatable(`#table-orders`, {
        ajax: AJAX.route(`POST`, `orders_api`),
        columns: [
            {data: `boxes`, title: `Numéro(s) Box(s)`},
            {data: `depositTickets`, title: `Ticket(s) consigne`},
            {data: `location`, title: `Emplacement`},
            {data: `totalBoxAmount`, title: `Montant total des Box`},
            {data: `totalDepositTicketAmount`, title: `Montant total des consignes`},
            {data: `totalCost`, title: `Balance`},
            {data: `user`, title: `Utilisateur`},
            {data: `client`, title: `Client`},
            {data: `date`, title: `Date et heure de création`},
            DATATABLE_ACTIONS
        ],
        order: [[`date`, `desc`]],
        listeners: {
            delete: data => deleteOrderModal.open(data),
        }
    });

    const newOrderModal = Modal.static(`#modal-new-order`, {
        ajax: AJAX.route(`POST`, `order_new`),
        table,
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
            }
        },
        success: () => {
            newOrderModal.close();
            confirmationOrderModal.open();
        }
    });

    const scanModal = Modal.static(`#modal-scan`);

    const confirmationOrderModal = Modal.static(`#modal-confirmation-order`);

    $(`.new-order`).click(() => {
        newOrderModal.open();
        $('.scan-box').removeClass('d-none');
        $('.deposit-ticket-container').addClass('d-none');
        $('select[name=box]').prop('disabled', false);
    });

    $(`.delete-order`).click(() => deleteOrderModal.open());

    $(`.scan-box`).click(function() {
        const $modal = $(this).closest('.modal');
        Scan.proceed(scanModal, $modal.find('select[name=box]'), `boxes`, {
            success: 'La Box a bien été ajoutée',
            warning: 'La Box n\'existe pas'
        });
    });

    $(`.deposit-ticket-scan`).click(function() {
        const $modal = $(this).closest('.modal');
        Scan.proceed(scanModal, $modal.find('select[name=depositTicket]'), `deposit_tickets`, {
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
