import "../styles/pages/client-order.scss";
import {$document} from "../app";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Modal from "../modal";
import {StringHelper, URL} from "../util";
import $ from "jquery";
import Flash, {SUCCESS, WARNING} from "../flash";

$(function() {
    initOrderDatatable();

    const params = URL.getRequestQuery()
    if (params.action === 'new') {
        openOrderNewModal();
    }
    else if (params.action === 'show'
             && params['action-data']) {
        openOrderShowModal(params['action-data']);
    }
    else if (params.action === 'edit'
             && params['action-data']) {
        openOrderEditModal(params['action-data']);
    }
    else if (params.action === 'validation'
             && params['action-data']) {
        openOrderValidationModal(params['action-data']);
    }

    $document.on('click', '.show-detail', function () {
        const $link = $(this);
        const clientOrderId = $link.data('id');
        const action = $link.data('action');
        setOrderRequestInURL(action, clientOrderId);
        if (action === 'show') {
            openOrderShowModal(clientOrderId);
        }
        else if (action === 'validation') {
            openOrderValidationModal(clientOrderId);
        }
    });

    $document.on(`change`, `[name="type"]`, function(){
        const $modal = $(this).closest(`.modal`);
        onTypeChange($modal);

        const type = $(this).data('code');

        const starterKit = JSON.parse($(`#starterKit`).val());
        if (starterKit) {
            if (type === 'PURCHASE_TRADE') {
                addBoxTypeToCart($modal, starterKit);
            } else {
                $modal.find('.box-type-line[data-id=' + starterKit.id + ']').remove();
            }
        }
    });

    $document.on(`change`, `[name="collectRequired"]`, function(){
        const $modal = $(this).closest(`.modal`);
        const $type = $modal.find('[name="type"]:checked');
        if ($type.data('code') === 'AUTONOMOUS_MANAGEMENT') {
            $modal.find('.crates-amount-to-collect-container').toggle();
            $modal.find('[name="cratesAmountToCollect"]').prop('required', $(this).is(':checked'));
        }
    });

    $document.on(`change`, `[name="client"]`, function(){
        const $modal = $(this).closest(`.modal`);
        updateModalFees($modal);
    });

    $document.on(`change`, `[name="date"]`, function(){
        const $date = $(this);
        const $modal = $date.closest(`.modal`);
        const $client = $modal.find('[name="client"]');
        const [clientData] = $client.select2('data');
        updateDeliveryFee(clientData, $date);
    });

    $document.on(`click`, `.add-box-to-cart-button`, function(){
        addSelectedBoxTypeToCart($(this).closest(`.modal`));
    })

    $document.on(`click`, `.cart-container .increase, .cart-container .decrease` , function(){
        updateInputValue($(this));
    });

    $document.on(`change`, `.cart-container input[name=quantity]`, function() {
        const $input = $(this);

        onBoxTypeQuantityChange($input);
        updateCrateNumberAverage($input.closest(`.modal`));
    })

    $document.on('click', `.add-box-type-model-button`, function () {
        addBoxTypeModel($(this).closest(`.modal`));
    });

    $document.on('click', `input[name=type]`, function () {
        const $modal = $(this).closest(`.modal`);
        $modal.find('.client-order-container').removeClass('d-none');
        $modal.find('.footer').removeClass('d-none');
    });

    console.log('huh');
    $(`.filters [name="from"]`).on(`change`, function() {
        let date = new Date(this.value);
        date.setDate(date.getDate() - 30);
        date = date.toISOString().substring(0, 10);

        $(`.filters [name="to"]`)
            .attr(`min`, date)
            .attr(`max`, this.value)
            .val(date);
    })
});

function openEditStatusModal(clientOrderId, editModal){
    const ajax = AJAX.route(`POST`, `client_order_edit_status_template`, {
        clientOrder: clientOrderId
    });
    Modal.load(ajax, {
        table: '#table-client-order',
        success: (res) => {
            getTimeLine(editModal.element, clientOrderId);
            if(res.hideEditStatusButton) {
                editModal.element.find('.edit-status-button').remove();
            }
        },
        afterHidden: () => {
            removeActionRequestInURL();
        },
        error: () => {
            removeActionRequestInURL();
        }
    });
}

function openOrderShowModal(clientOrderId) {
    const ajax = AJAX.route(`POST`, `client_order_show_template`, {
        clientOrder: clientOrderId
    });

    Modal.load(ajax, {
        afterOpen: (modal) => {
            getTimeLine(modal.element, clientOrderId);

            const $statusEditButton = modal.element.find('.edit-status-button');
            $statusEditButton.off('click');
            $statusEditButton.on('click', () => {
                openEditStatusModal(clientOrderId, modal);
            });
        },
        afterHidden: () => {
            removeActionRequestInURL();
        },
        error: () => {
            removeActionRequestInURL();
        },
    });
}

function openOrderEditModal(clientOrderId) {
    const ajax = AJAX.route(`GET`, `client_order_edit_template`, {clientOrder: clientOrderId});
    Modal.load(ajax, {
        table: '#table-client-order',
        success: (response) => {
            openOrderValidationModal(clientOrderId, response.validationTemplate);
        },
        afterOpen: (modal) => {
            const cartContentStr = modal.element.find('[name="cart-content"]').val();
            const cartContent = cartContentStr && JSON.parse(cartContentStr);

            for (const line of cartContent) {
                addBoxTypeToCart(modal.element, line);
            }

            updateModalFees(modal.element);
            onTypeChange(modal.element);

            const newUrl = URL.createRequestQuery({
                action: 'edit',
                'action-data': clientOrderId
            });
            URL.pushState(document.title, newUrl);
        },
        afterHidden: () => {
            removeActionRequestInURL();
        },
        error: () => {
            removeActionRequestInURL();
        },
    });
}

function openOrderValidationModal(clientOrderId, modalContent = null) {
    const content = (
        modalContent
        || AJAX.route(`POST`, `client_order_validation_template`, {clientOrder: clientOrderId})
    );

    Modal.load(content, {
        submit: Routing.generate(`client_order_validation`, {clientOrder: clientOrderId}),
        table: '#table-client-order',
        afterOpen: () => {
            const newUrl = URL.createRequestQuery({
                action: 'validation',
                'action-data': clientOrderId
            });
            URL.pushState(document.title, newUrl);
        },
        afterHidden: () => {
            removeActionRequestInURL();
        },
        onPrevious: () => {
            openOrderEditModal(clientOrderId);
        },
        error: () => {
            removeActionRequestInURL();
        }
    });
}

function openOrderNewModal() {
    const newClientOrderModal = Modal.static(`#modal-new-client-order`, {
        ajax: AJAX.route(`POST`, `client_order_new`),
        table: '#table-client-order',
        success: (response) => {
            openOrderValidationModal(response.clientOrderId, response.validationTemplate);
        },
        afterHidden: () => {
            removeActionRequestInURL();
        }
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
            delete: (data, $button) => {
                const ajax = AJAX.route(`POST`, `client_order_delete_template`, {
                    clientOrder: $button.data(`id`),
                });

                Modal.load(ajax, {table});
            },
        }
    });
    return table;
}

function setOrderRequestInURL(action, order) {
    const getParams = URL.getRequestQuery()
    if (getParams.action !== action
        && getParams['action-data'] !== `${order}`) {
        getParams.action = action;
        getParams['action-data'] = order;

        URL.pushState(document.title, URL.createRequestQuery(getParams));
    }
}

function removeActionRequestInURL() {
    const getParams = URL.getRequestQuery()
    if (getParams.hasOwnProperty('action')
        || getParams.hasOwnProperty('action-data')) {
        delete getParams.action;
        delete getParams['action-data'];

        URL.replaceState(document.title, URL.createRequestQuery(getParams));
    }
}

function onBoxTypeQuantityChange($input) {
    const $row = $input.closest('.cart-box');
    const unitPrice = $row.find('[name="unitPrice"]').val();
    const quantity = $input.val() > 0 ? $input.val() : 1;
    const $totalPrice = $row.find('.totalPrice');
    const totalPrice = unitPrice * quantity;
    const totalPriceStr = StringHelper.formatPrice(totalPrice);

    $totalPrice.text(totalPriceStr+" €");
}

function addBoxTypeModel($modal) {
    const selectedClientId = $modal.find('[name="client"]').val();
    if (selectedClientId) {
        AJAX.route(`GET`, `crate_pattern_lines`, {client: selectedClientId})
            .json()
            .then((res) => {
                const boxTypes = res.box_types;
                if (boxTypes.length > 0) {
                    for (const boxType of boxTypes) {
                        addBoxTypeToCart($modal, boxType);
                    }
                    updateCrateNumberAverage($modal);
                    Flash.add(SUCCESS, 'Le modèle de caisse a bien été ajouté au panier.');
                }
                else {
                    Flash.add(WARNING, 'Le modèle de caisse du client sélectionné est vide.');
                }
            });
    }
    else {
        Flash.add(WARNING, `Le client de la commande n'est pas sélectionné.`);
    }
}

function getTimeLine($modal, clientOrder) {
    AJAX.route(`GET`, `client_order_history_api`, {clientOrder})
        .json()
        .then(res => $modal.find(`.client-order-history`).empty().append(res.template));
}

function addSelectedBoxTypeToCart($modal) {
    const $select2 = $modal.find('[name="boxType"]');
    const [typeBoxData] = $select2.select2('data');
    const defaultCrateType = $modal.find('[name=defaultCrateType]').val();
    if(typeBoxData.volume > defaultCrateType) {
        Flash.add(WARNING, `Le volume du type de Box <strong>${typeBoxData.name}</strong> est supérieur à celui du type de caisse par défaut`)
    } else {
        if (typeBoxData && !$modal.find(`.cart-container > [data-id=${typeBoxData.id}]`).exists()) {
            addBoxTypeToCart($modal, typeBoxData, true);
        }
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
            : '<span class="box-type-image"></span>';
        const initialQuantity = typeBoxData.quantity || 1;
        const volume = typeBoxData.volume || 0;
        const $boxTypeLine = $(`
            <div class="cart-box box-type-line my-2 row" data-id="${typeBoxData.id}">
                <span class="col-auto">
                    <div class="box-type-image">${boxTypeImage}</div>
                </span>
                <div class="col">
                <div class="row type-box-input">
                    <button class="col-3 secondary decrease">-</button>
                    <input type="number" name="quantity" value="${initialQuantity}" min="1" max="9999" class="data-array col-6 cart-box-number">
                    <button class="col-3 secondary increase">+</button>
                </div>
                </div>
                <span class="col text-big">${typeBoxData.name}</span>
                <input name="unitPrice" class="data-array" value="${unitPrice} " type="hidden"/>
                <input name="volume" value="${volume}" type="hidden"/>
                <span class="col-2 unit-price-item">T.U. ${unitPriceStr} €</span>
                <span class="totalPrice col-auto text-big"></span>
                <button class="remove d-inline-flex" value="${typeBoxData.id}"><i class="bxi bxi-trash-circle col"></i></button>
                <input type="hidden" name="boxTypeId" class="data-array" value="${typeBoxData.id}">
            </div>
        `);
        $cartContainer.append($boxTypeLine);

        $boxTypeLine.find(".remove").on('click', function(){
            $boxTypeLine.remove();
            if(!$modal.find(`.box-type-line`).exists()) {
                $modal.find(".empty-cart").removeClass(`d-none`);
                $modal.find(".crates-amount-label").addClass('d-none').empty();
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

    $input.trigger(`change`);
}

function updateDeliveryFee(clientData, $date) {
    const $modal = $date.closest('.modal');
    const $deliveryPrice = $modal.find(".deliveryPrice");
    if (clientData) {
        const date = new Date($date.val());
        // week-end
        const workFreeDays = JSON.parse($modal.find('[name="workFreeDay"]').val());
        let isFreeDay = false;
        workFreeDays.forEach(function (workFreeDay){
            if(workFreeDay[0] === date.getDate() && workFreeDay[1] === date.getMonth()+1){
                isFreeDay = true;
            }
        })
        const deliveryFee = (date.getDay() === 6 || date.getDay() === 0 || isFreeDay)
            ? clientData.nonWorkingRate
            : clientData.workingRate;
        $deliveryPrice.text(`Frais de transport (HT) ${StringHelper.formatPrice(deliveryFee)}`);
    }
    else {
        $deliveryPrice.text('');
    }
}

function updateCrateNumberAverage($modal) {
    const clientId = $modal.find('[name="client"]').val();
    const cart = $modal.find('.box-type-line')
        .map((_, line) => {
            const $line = $(line);
            return {
                quantity: $line.find('[name="quantity"]').val(),
                boxType: $line.find('[name="boxTypeId"]').val()
            }
        })
        .toArray();

    if (clientId && cart && cart.length > 0) {
        AJAX.route('GET', 'get_crates_amount', {client: clientId, cart})
            .json()
            .then(({cratesAmount}) => {
                const $crateAmountLabel = $modal.find('.crates-amount-label');
                if (cratesAmount) {
                    const sCrate = cratesAmount > 1 ? 's' : '';
                    $crateAmountLabel.text(`Représente environ ${cratesAmount} caisse${sCrate}`);
                    $crateAmountLabel.removeClass('d-none');
                }
                else {
                    $crateAmountLabel.text('');
                    $crateAmountLabel.addClass('d-none');
                }
            });
    }
}

function updateModalFees($modal) {
    const $client = $modal.find('[name="client"]');
    const [clientData] = $client.select2('data');
    const $clientAddress = $modal.find(`.client-address`);
    const $servicePrice = $modal.find(`.servicePrice`);

    if(clientData) {
        $clientAddress.text(clientData.address);
        $servicePrice.text("Frais de service " + StringHelper.formatPrice(clientData.serviceCost));
        $('.transport').find('input[name=deliveryMethod]').each(function() {
            if(parseInt($(this).val()) === parseInt(clientData.deliveryMethod)) {
                $(this).prop('checked', true);
            }
        });
    } else {
        $clientAddress.text("");
        $servicePrice.text("");
    }
    updateDeliveryFee(clientData, $modal.find('[name="date"]'));
}

function onTypeChange($modal) {
    const $type = $modal.find('[name="type"]:checked');
    const type = $type.data('code');
    const $autonomousManagement = $modal.find('.autonomous-management');
    $modal.find('[name="cratesAmountToCollect"]').prop('required', false);
    $autonomousManagement.addClass('d-none');
    $modal.find('.cratesAmountToCollect').prop('required', false);
    $modal.find('.crates-amount-to-collect-container').hide();
    if(type === 'AUTONOMOUS_MANAGEMENT'){
        const $collect = $modal.find('[name="collect"]');
        $autonomousManagement.removeClass('d-none');

        if($collect && $collect.val() === "1"){
            $modal.find('[name="collectRequired"]').prop('checked', true);
            $modal.find('.crates-amount-to-collect-container').show();
        }
    }
}
