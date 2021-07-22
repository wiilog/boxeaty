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
    if (getParams.new === '1') {
        removeOrderRequestInURL();
        openNewClientOrderModal();
    }
    else if (getParams.order) {
        removeNewRequestInURL();
        openOrderEditModal(getParams.order);
    }

    $document.on('click', '.show-detail', function () {
        const $link = $(this);
        const clientOrderId = $link.data('id');
        setOrderRequestInURL(clientOrderId);
        openOrderEditModal(clientOrderId);
    })

    $(`#new-client-order`).on('click', function(){
        window.location.href = Routing.generate(`client_orders_list`, {new: 1})
    });

    $modal.find('[name="type"]').on('change', function(){
        const type = $(this).val();

        $modal.find('.collectNumber').hide();
        if(type === 'AUTONOMOUS_MANAGEMENT'){
            $modal.find('.autonomousManagement').removeClass('d-none');
            $modal.find('[name="collect"]').on('change', function(){
                $modal.find('.collectNumber').toggle();
        });
        } else{
            $modal.find('.autonomousManagement').addClass('d-none');
        }
    })

    $modal.find('[name="client"]').on('change', function(){
        const clientData = $(this).select2('data')[0];
        if(clientData) {
            $(`#clientAddress`).text(clientData.address);
            $modal.find('[name="date"]').on('change', function(){
                const $date = new Date($modal.find('[name="date"]').val());
                const deliveryFee = ($date.getDay() === 6 || $date.getDay() === 7)
                    ? clientData.nonWorkingRate
                    : clientData.workingRate;
                $("#deliveryPrice").text(`Frais de transport (HT) ${deliveryFee}`);
            });
            $("#servicePrice").text("Frais de service " + clientData.serviceCost);
        } else{
            $(`#clientAddress`).text("");
        }
    })

    $modal.find(".add-box-to-cart-button").on('click', function(){
        addSelectedBoxTypeToCart($modal);
    })

    $modal.on('keyup','[name="quantity"]' , function(){
        onBoxTypeQuantityChange($(this));
    });

    $modal.find('.add-box-type-model-button').on('click', function () {
        addBoxTypeModel($modal);
    });

    // TODO REMOVE
    if($modal.find('#redirection').val() == 1){

    }else if($modal.find('#redirection').val() == 2){
        openValidationClientOrderModal();
    }
});

function openOrderEditModal(clientOrderId) {
    const ajax = AJAX.route(`POST`, `client_order_show_template`, {
        clientOrder: clientOrderId
    });

    Modal.load(ajax, {
        afterHidden: () => {
            removeOrderRequestInURL();
        },
        error: () => {
            removeOrderRequestInURL();
        }
    });
}

function openNewClientOrderModal() {
    const newClientOrderModal = Modal.static(`#modal-new-client-order`, {
        ajax: AJAX.route(`POST`, `client_order_new`),
        success: (response) => {
            Modal.load(response.template);
        },
        afterHidden: () => {
            removeNewRequestInURL();
        },
    });
    newClientOrderModal.open();
}

function openValidationClientOrderModal(clientOrder) {
    const validationClientOrderModal = Modal.load(AJAX.route(`POST`, `client_order_validate_template`, {clientOrder:clientOrder}), );

}

function initOrderDatatable() {
    return initDatatable(`#table-client-order`, {
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
}

function setOrderRequestInURL(order) {
    const getParams = URL.getRequestQuery()
    if (getParams.order !== String(order)) {
        getParams.order = order;
        const newUrl = URL.createRequestQuery(getParams);
        URL.pushState(document.title, newUrl);
    }
}

function removeOrderRequestInURL() {
    const getParams = URL.getRequestQuery()
    if (getParams.hasOwnProperty('order')) {
        delete getParams.order;
        const newUrl = URL.createRequestQuery(getParams);
        URL.pushState(document.title, newUrl);
    }
}

function removeNewRequestInURL() {
    const getParams = URL.getRequestQuery()
    if (getParams.hasOwnProperty('new')) {
        delete getParams.new;
        const newUrl = URL.createRequestQuery(getParams);
        URL.pushState(document.title, newUrl);
    }
}

function onBoxTypeQuantityChange($quantity) {
    const $row = $quantity.closest('.cartBox');
    const unitPrice = $row.find('[name="unitPrice"]').val();
    const quantity = $quantity.val();

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
        addBoxTypeToCart($modal, typeBoxData);
    }
    $select2.val(null).trigger("change");
}

function addBoxTypeToCart($modal, typeBoxData) {
    const unitPrice = typeBoxData.price || typeBoxData.unitPrice || 0;
    const unitPriceStr = StringHelper.formatPrice(unitPrice);

    const $cartContainer = $modal.find(".cart-container");
    const $alreadyAddedBox = $cartContainer.find(`.cartBox[data-id="${typeBoxData.id}"]`);
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
        const $boxTypeLine = $(`
            <div class="cartBox my-2 row" data-id="${typeBoxData.id}">
                <span class="col-auto">
                    <div class="box-type-image">${boxTypeImage}</div>
                </span>
                <div class="col-2">
                    <input type="number" name="quantity" value="${initialQuantity}" min="1" max="1000" class="data-array cartBoxNumber">
                </div>
                <span class="col bigTxt">${typeBoxData.name}</span>
                <input name="unitPrice" class="data-array" value="${unitPrice}" type="hidden"/>
                <span class="col-2 unit-price-item">T.U. ${unitPriceStr}</span>
                <span class="totalPrice col-2 bigTxt"></span>
                <button class="remove d-inline-flex" value="${typeBoxData.id}"><i class="bxi bxi-trash-circle col"></i></button>
                <input type="hidden" name="boxTypeId" class="data-array" value="${typeBoxData.id}">
            </div>
        `);
        $cartContainer.append($boxTypeLine);

        $boxTypeLine.find(".remove").on('click', function(){
            $boxTypeLine.remove();
            if(!$modal.find(`.cartBox`).exists()) {
                $modal.find(".emptyCart").removeClass(`d-none`);
            }
        });

        onBoxTypeQuantityChange($boxTypeLine.find('[name="quantity"]'));
    }

    if($modal.find(`.cartBox`).exists()) {
        $modal.find(".emptyCart").addClass(`d-none`);
    }
}
