import "../styles/pages/client-order.scss";
import {$document} from "../app";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Modal from "../modal";
import {StringHelper, URL} from "../util";
import $ from "jquery";
import Flash from "../flash";


$(function() {
    const $modal = $(`#modal-new-client-order`);
    initOrderDatatable();

    const getParams = URL.getRequestQuery()
    if (getParams.action === 'new') {
        openNewClientOrderModal();
    }
    else if (getParams.action === 'show'
             && getParams['action-data']) {
        openOrderShowModal(getParams['action-data']);
    }
    else if (getParams.action === 'validation'
             && getParams['action-data']) {
        openOrderValidationModal(getParams['action-data']);
    }

    $('#modal-validation-client-order').find('.submit-button').on('click', function(){
        window.location.href = Routing.generate('client_order_validation_template', {action : 'validation'});
    });

    $document.on('click', '.show-detail', function () {
        const $link = $(this);
        const clientOrderId = $link.data('id');
        setOrderRequestInURL(clientOrderId); // TODO ALEX action = show or validation en fonction du statut de la commande
        openOrderShowModal(clientOrderId);
    })

    $(`#new-client-order`).on('click', function(){
        window.location.href = Routing.generate(`client_orders_list`, {action: 'new'})
    });

    let toggleCratesAmountToCollect = false;
    $modal.find('[name="type"]').on('change', function(){
        const type = $(this).data('code');

        $modal.find('.crates-amount-to-collect-container').hide();
        $modal.find('[name="collectRequired"]').prop('checked', false);
        $modal.find('[name="cratesAmountToCollect"]').prop('required', false);
        const $autonomousManagement = $modal.find('.autonomous-management');
        if(type === 'AUTONOMOUS_MANAGEMENT'){
            toggleCratesAmountToCollect = true;
            $autonomousManagement.removeClass('d-none');
        } else{
            toggleCratesAmountToCollect = false;
            $autonomousManagement.addClass('d-none');
        }
    });

    $modal.find('[name="collectRequired"]').on('change', function(){
        if (toggleCratesAmountToCollect) {
            $modal.find('.crates-amount-to-collect-container').toggle();
            $modal.find('[name="cratesAmountToCollect"]').prop('required', $(this).is(':checked'));
        }
    });

    $(document).arrive(`.increase, .decrease`, function() {
        $(this).on('click', function() {
            updateInputValue($(this));
        });
    });

    let clientData
    $modal.find('[name="client"]').on('change', function(){
        clientData = $(this).select2('data')[0];
        const $clientAddress = $(`#clientAddress`);
        const $servicePrice = $(`#servicePrice`);
        if(clientData) {
            $clientAddress.text(clientData.address);
            $servicePrice.text("Frais de service " + StringHelper.formatPrice(clientData.serviceCost));
        } else{
            $clientAddress.text("");
            $servicePrice.text("");
        }
        updateDeliveryFee(clientData, $modal.find('[name="date"]'));
    });
    $modal.find('[name="date"]').on('change', function(){
        updateDeliveryFee(clientData, $(this));
    });

    $modal.find(".add-box-to-cart-button").on('click', function(){
        addSelectedBoxTypeToCart($modal);
    })

    $modal.on('click','.increase, .decrease' , function(){
        onBoxTypeQuantityChange($(this));
        updateCrateNumberAverage($modal);
    });

    $modal.find('.add-box-type-model-button').on('click', function () {
        addBoxTypeModel($modal);
    });

    $modal.find('input[name=type]').on('click', function () {
        $modal.find('.client-order-container').removeClass('d-none');
        $modal.find('.footer').removeClass('d-none');
    });
});

function openOrderShowModal(clientOrderId) {
    const ajax = AJAX.route(`POST`, `client_order_show_template`, {
        clientOrder: clientOrderId
    });

    Modal.load(ajax, {
        afterHidden: () => {
            removeActionRequestInURL();
        },
        error: () => {
            removeActionRequestInURL();
        }
    });
}

function openOrderValidationModal(clientOrderId) {
    const ajax = AJAX.route(`POST`, `client_order_validation_template`, {
        clientOrder: clientOrderId,
    });

    Modal.load(ajax, {
        afterHidden: () => {
            removeActionRequestInURL();
        },
        error: () => {
            removeActionRequestInURL();
        }
    });
}

function openNewClientOrderModal() {
    const newClientOrderModal = Modal.static(`#modal-new-client-order`, {
        ajax: AJAX.route(`POST`, `client_order_new`),
        success: (response) => {
            removeActionRequestInURL();
            Modal.load(response.validationTemplate, {
                afterOpen: modal=> {
                    $('#modal-validation-client-order').find('.submit-button').on('click', function(){
                        window.location.href = Routing.generate('client_orders_list');
                    });
                },
            });
        },
        afterHidden: () => {
            removeActionRequestInURL();
        },
    });
    newClientOrderModal.open();
}

function initOrderDatatable() {
    const table = initDatatable(`#table-client-order`, {
        ajax: AJAX.route(`POST`, `client_orders_api`),
        scrollX: false,
        columns: [
            {data: `col`, orderable: false},
        ],
        listeners: {
            delete: (data) => {
                const ajax = AJAX.route(`POST`, `client_order_delete_template`, {
                    clientOrder: data.id
                });

                Modal.load(ajax, {table});
            },
        }
    });
    return table;
}

function setOrderRequestInURL(order) {
    const getParams = URL.getRequestQuery()
    if (getParams.action !== 'show'
        && getParams['action-data'] !== `${order}`) {
        getParams.action = 'show';
        getParams['action-data'] = order;
        const newUrl = URL.createRequestQuery(getParams);
        URL.pushState(document.title, newUrl);
    }
}

function removeActionRequestInURL() {
    const getParams = URL.getRequestQuery()
    if (getParams.hasOwnProperty('action')
        || getParams.hasOwnProperty('action-data')) {
        delete getParams.action;
        delete getParams['action-data'];
        const newUrl = URL.createRequestQuery(getParams);
        URL.pushState(document.title, newUrl);
    }
}

function onBoxTypeQuantityChange($quantity) {
    const $row = $quantity.closest('.cart-box');
    const unitPrice = $row.find('[name="unitPrice"]').val();
    const $input = $quantity.siblings('input').first();
    const quantity = $input.val();

    const $totalPrice = $row.find('.totalPrice');
    const totalPrice = unitPrice * quantity;
    const totalPriceStr = StringHelper.formatPrice(totalPrice);
    $totalPrice.text(totalPriceStr);

}

function addBoxTypeModel($modal) {
    const selectedClientId = $modal.find('[name="client"]').val();
    if (selectedClientId) {
        AJAX
            .route(`GET`, `client_box_types`, {client: selectedClientId})
            .json()
            .then((res) => {
                const boxTypes = res['box-types'];
                if (boxTypes.length > 0) {
                    for (const boxType of boxTypes) {
                        addBoxTypeToCart($modal, boxType);
                    }
                    updateCrateNumberAverage($modal);
                    Flash.add('success', 'Le modèle de caisse a bien été ajouté au panier.');
                }
                else {
                    Flash.add('warning', 'Le modèle de caisse du client sélectionné est vide.');
                }
            });
    }
    else {
        Flash.add('warning', `Le client de la commande n'est pas sélectionné.`);
    }
}

function addSelectedBoxTypeToCart($modal) {
    const $select2 = $modal.find('[name="boxType"]');
    const [typeBoxData] = $select2.select2('data');
    if (typeBoxData
        && !$modal.find(`.cart-container > [data-id=${typeBoxData.id}]`).exists()) {
        addBoxTypeToCart($modal, typeBoxData, true);
    }
    $select2.val(null).trigger("change");
}

function addBoxTypeToCart($modal, typeBoxData, calculateAverageCrateNumber = false) {
    const unitPrice = typeBoxData.price || typeBoxData.unitPrice || 0;
    const unitPriceStr = StringHelper.formatPrice(unitPrice);

    const $cartContainer = $modal.find(".cart-container");
    const $alreadyAddedBox = $cartContainer.find(`.cart-box[data-id="${typeBoxData.id}"]`);
    if ($alreadyAddedBox.exists()) {
        const $quantity = $alreadyAddedBox.find('[name="quantity"]');
        const $unitPrice = $alreadyAddedBox.find('[name="unitPrice"]');
        const $unitPriceItem = $alreadyAddedBox.find('.unit-price-item');

        const currentQuantity = Number($quantity.val()) || 0;
        $quantity.val(typeBoxData.quantity + currentQuantity);
        $unitPrice.val(unitPrice);
        $unitPriceItem.text(`T.U. ${unitPriceStr}`);
        onBoxTypeQuantityChange($quantity);
    }
    else {
        const boxTypeImage = typeBoxData.image
            ? `
                <img src="/${typeBoxData.image}"
                     class="box-type-image"
                     alt="image"/>
            `
            : '';
        const initialQuantity = typeBoxData.quantity || 1;
        const volume = typeBoxData.volume || 0;
        const $boxTypeLine = $(`
            <div class="cart-box box-type-line my-2 row" data-id="${typeBoxData.id}">
                <span class="col-auto">
                    <div class="box-type-image">${boxTypeImage}</div>
                </span>
                <div class="col-2">
                <div class="row type-box-input">
                    <button class="col-3 secondary decrease">-</button>
                    <input type="number" name="quantity" value="${initialQuantity}" min="1" max="1000" class="data-array col-6 cartBoxNumber">
                    <button class="col-3 secondary increase">+</button>
                </div>
                </div>
                <span class="col bigTxt">${typeBoxData.name}</span>
                <input name="unitPrice" class="data-array" value="${unitPrice}" type="hidden"/>
                <input name="volume" value="${volume}" type="hidden"/>
                <span class="col-2 unit-price-item">T.U. ${unitPriceStr}</span>
                <span class="totalPrice col-2 bigTxt"></span>
                <button class="remove d-inline-flex" value="${typeBoxData.id}"><i class="bxi bxi-trash-circle col"></i></button>
                <input type="hidden" name="boxTypeId" class="data-array" value="${typeBoxData.id}">
            </div>
        `);
        $cartContainer.append($boxTypeLine);

        $boxTypeLine.find(".remove").on('click', function(){
            $boxTypeLine.remove();
            if(!$modal.find(`.box-type-line`).exists()) {
                $modal.find(".empty-cart").removeClass(`d-none`);
            }
        });

        onBoxTypeQuantityChange($boxTypeLine.find('[name="quantity"]'));
    }

    if($modal.find(`.cart-box`).exists()) {
        $modal.find(".empty-cart").addClass(`d-none`);
    }

    if (calculateAverageCrateNumber) {
        updateCrateNumberAverage($modal);
    }
}

function updateInputValue($button) {
    const $input = $button.siblings('input').first();
    const value = parseInt($input.val());
    if($button.hasClass('increase') && value !== 1000){
        $input.val(value+1);
    } else if($button.hasClass('decrease') && value !== 1) {
        $input.val(value-1);
    }
}

function updateDeliveryFee(clientData, $date) {
    const $deliveryPrice = $("#deliveryPrice");
    if (clientData) {
        const date = new Date($date.val());
        // week-end
        const deliveryFee = (date.getDay() === 6 || date.getDay() === 0)
            ? clientData.nonWorkingRate
            : clientData.workingRate;
        $deliveryPrice.text(`Frais de transport (HT) ${StringHelper.formatPrice(deliveryFee)}`);
    }
    else {
        $deliveryPrice.text('');
    }
}

function updateCrateNumberAverage($modal) {
    AJAX
        .route('GET', 'get_crate_average_volume')
        .json()
        .then(({average}) => {
            let boxesVolume = [];
            $modal
                .find('.cart-container .box-type-line')
                .each(function() {
                    const $line = $(this);
                    const quantity = Number($line.find('[name="quantity"]').val()) || 0;
                    const volume = Number($line.find('[name="volume"]').val()) || 0;
                    boxesVolume.push(
                        (quantity && volume)
                            ? (quantity * volume)
                            : null
                    );
                });
            boxesVolume = boxesVolume
                .filter((volume) => volume)
                .reduce((sum, volume) => (sum + volume), 0);

            const crateAverage = boxesVolume
                ? Math.ceil(boxesVolume / average)
                : null;
            const $crateNumberAverage = $modal.find('.crate-number-average');

            if (crateAverage) {
                const crateAverageInt = Math.ceil(crateAverage);
                $crateNumberAverage.text(`Représente environ ${crateAverageInt} caisse${crateAverageInt > 1 ? 's' : ''}`);
            }
            else {
                $crateNumberAverage.text('');
            }
        });
}
