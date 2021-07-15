import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Modal from "../modal";

import "../styles/pages/client-show.scss";

$(document).ready(() => {
    getBoxTypes();
    getBoxRecurrence();

    $(`.edit-client`).click(() => {
        const ajax = AJAX.route(`POST`, `client_edit_template`, {
            client: $('#client-id').val()
        });
        Modal.load(ajax);
    });

    $(`.delete-client`).click(() => {
        const ajax = AJAX.route(`POST`, `client_delete_template`, {
            client: $('#client-id').val()
        });
        Modal.load(ajax, {
            success : () =>{
                window.location.href = Routing.generate(`clients_list`);
            }
        })
    });

    const addClientBoxTypeModal = Modal.static(`#modal-add-client-box-type`, {
        ajax: AJAX.route(`POST`, `add_client_box_type`),
        success: () => {
            getBoxTypes();
        }
    });

    $(`.add-client-box-type`).click(() => addClientBoxTypeModal.open());

    $(document).arrive(`.delete-client-box-type`, function () {
        $(this).click(() => {
            AJAX.route(`POST`, `delete_client_box_type`, {
                id: $(this).data('id'),
            }).json((result) => {
                if(result.success) {
                    getBoxTypes();
                }
            })
        });
    });

    $(document).arrive(`.edit-client-box-type`, function () {
        $(this).click(() => {
            const ajax = AJAX.route(`POST`, `client_box_type_edit_template`, {
                clientBoxType: $(this).data('id'),
            });

            Modal.load(ajax);
        });
    });
});

function getBoxTypes() {
    AJAX.route(`GET`, `box_types_api`, {
        id: $('#client-id').val()
    }).json((response) => {
        $('.box-type-card-wrapper').empty().append(response.template);
        $('.total-crate-type-price').text(response.totalCrateTypePrice + '€');
    });
}

function getBoxRecurrence() {
    AJAX.route(`GET`, `order_recurrence_api`, {
        id: $('#client-id').val()
    }).json((response) => {
        $('.order-recurrence-container').empty().append(response.template);
    });
}