import "../styles/pages/client-order.scss";
import {$document} from "../app";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Modal from "../modal";
import {StringHelper, URL} from "../util";
import $ from "jquery";
import Flash from "../flash";
import {DateTools} from "../util";


$(function() {
    DateTools.manageDateLimits(`input[name=from]`, `input[name=to]`, 20);

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

    let toggleCratesAmountToCollect = false;
    $document.on(`change`, `[name="type"]`, function(){
        const $modal = $(this).closest(`.modal`);
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

        const starterKit = JSON.parse($(`#starterKit`).val());
        if (starterKit) {
            if (type === 'PURCHASE_TRADE') {
                addBoxTypeToCart($modal, starterKit);
            } else {
                $modal.find('.box-type-line[data-id=' + starterKit.id + ']').remove();
                $modal.find(`.empty-cart`).removeClass(`d-none`);
            }
        }
    });

    $document.on(`change`, `[name="collectRequired"]`, function(){
        const $modal = $(this).closest(`.modal`);

        if (toggleCratesAmountToCollect) {
            $modal.find('.crates-amount-to-collect-container').toggle();
            $modal.find('[name="cratesAmountToCollect"]').prop('required', $(this).is(':checked'));
        }
    });

    let clientData;
    $document.on(`change`, `[name="client"]`, function(){
        const $modal = $(this).closest(`.modal`);
        clientData = $(this).select2('data')[0];
        const $clientAddress = $modal.find(`.clientAddress`);
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
    });

    $document.on(`change`, `[name="date"]`, function(){
        updateDeliveryFee(clientData, $(this));
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

function setOrderRequestInURL(action, order) {
    const getParams = URL.getRequestQuery()
    if (getParams.action !== action
        && getParams['action-data'] !== `${order}`) {
        getParams.action = action;
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
        URL.replaceState(document.title, newUrl);
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
        AJAX
            .route(`GET`, `crate_pattern_lines`, {client: selectedClientId})
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

function getTimeLine($modal, $clientOrder) {
    AJAX
        .route(`GET`, `client_order_history_api`, {id: $clientOrder})
        .json()
        .then((res) => {
            $modal.find(`.client-order-history`).empty().append(res.template);
        });
}

function addSelectedBoxTypeToCart($modal) {
    const $select2 = $modal.find('[name="boxType"]');
    const [typeBoxData] = $select2.select2('data');
    if (typeBoxData && !$modal.find(`.cart-container > [data-id=${typeBoxData.id}]`).exists()) {
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
            : '<span class="box-type-image"></span>';
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
                <input name="unitPrice" class="data-array" value="${unitPrice} " type="hidden"/>
                <input name="volume" value="${volume}" type="hidden"/>
                <span class="col-2 unit-price-item">T.U. ${unitPriceStr} €</span>
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
