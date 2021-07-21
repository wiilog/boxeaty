import "../styles/pages/client-order.scss";
import {$document} from "../app";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";
import Modal from "../modal";
import {URL} from "../util";

$(function() {
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
