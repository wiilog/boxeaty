import '../app';
import $ from "jquery";

$(document).ready(() => {
    $('.export-autonomous-management').on('click', function () {
        if ($("input[name=from]").val() != "" && $("input[name=to]").val() != "") {
            window.location.href = Routing.generate('client_order_export_autonomous_management', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });

    $('.export-one-time-service').on('click', function () {
        if ($("input[name=from]").val() != "" && $("input[name=to]").val() != "") {
            window.location.href = Routing.generate('client_order_export_one_time_service', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });

    $('.export-trade-service').on('click', function () {
        if ($("input[name=from]").val() != "" && $("input[name=to]").val() != "") {
            window.location.href = Routing.generate('client_order_export_purchase_trade_service', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });
});
