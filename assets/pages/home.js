import $ from "jquery";

$(function() {
    $(`#new-client-order`).on('click', () => {
        window.location.href = Routing.generate(`client_orders_list`, {action: 'new'});
    });
});