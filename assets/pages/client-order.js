import "../styles/pages/client-order.scss";
import {$document} from "../app";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Modal from "../modal";
import {URL} from "../util";
import $ from "jquery";


$(function() {
    const $modal = $(`#modal-new-client-order`);
    initOrderDatatable();

    const getParams = URL.getRequestQuery()
    if (getParams.order) {
        openOrderEditModal(getParams.order);
    }

    $document.on('click', '.show-detail', function () {
        const $link = $(this);
        const clientOrderId = $link.data('id');
        setOrderRequestInURL(clientOrderId);
        openOrderEditModal(clientOrderId);
    })

    $(`#new-client-order`).click(function(){
        window.location.href = Routing.generate(`client_orders_list`, {
            redirection: '1'
        })
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
                if($date.getDay() === 6 || $date.getDay() === 7) {
                    $("#deliveryPrice").text("Frais de transport (HT) " + clientData.nonWorkingRate );
                }else{
                    $("#deliveryPrice").text("Frais de transport (HT) " + clientData.workingRate );
                }
            });
            $("#servicePrice").text("Frais de service " + clientData.serviceCost );
        } else{
            $(`#clientAddress`).text("");
        }
    })

    $modal.find("#addBoxes").on('click', function(){
        const $select2 = $modal.find('[name="boxType"]');
        const typeBoxData = $select2.select2('data')[0];
        if(!$modal.find(`#boxes>[data-id = ${typeBoxData.id} ]`).exists()) {
            $modal.find("#boxes").append(`
                <div class="cartBox mb-2 m-1 row" data-id="${typeBoxData.id}">
                    <i class="col"></i>
                    <input type="number" name="quantity" value="1" min="1" max="1000" class="data-array col cartBoxNumber">
                    <span class="col bigTxt">${typeBoxData.text}</span>
                    <span class="col">T.U ${typeBoxData.price} €</span>
                    <span class="totalPrice col bigTxt"  > ${typeBoxData.price} €</span>
                    <button class="remove" value="${typeBoxData.id}"><i class="bxi bxi-trash-circle col"></i></button>
                </div>
                <input type="hidden" name="boxTypeId" class="data-array">`
            );
        }
        $select2.val(null).trigger("change");

        $modal.find(".remove").on('click', function(){
            const remove = $(this).val();
            $modal.find(`#boxes>[data-id = ${remove} ]`).remove();
            if(!$modal.find(`.cartBox`).exists()) {
                $modal.find(".emptyCart").removeClass(`d-none`);
            }
        })
        if($modal.find(`#cartBox`)) {
            $modal.find(".emptyCart").addClass(`d-none`);
        }
    })

    $modal.on('keyup',"[name = quantity]" , function(){
        const typeBoxData = $modal.find('[name="boxType"]').select2('data')[0];
        const totalPrice = typeBoxData.price * $(this).val();
        $(this).siblings(".totalPrice").text(`${totalPrice} €`);
    })

    if($modal.find('#redirection').val() == 1){
        openNewClientOrderModal();
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
    })
}

function openNewClientOrderModal() {
    const newClientOrderModal = Modal.static(`#modal-new-client-order`, {
        ajax: AJAX.route(`POST`, `client_order_new`),
        success: (response)=>{console.log(response); openValidationClientOrderModal(response.clientOrder); },
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
