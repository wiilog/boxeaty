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
            $('.export-autonomous-management').removeClass('disabled');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
            $('.export-autonomous-management').addClass('disabled');
        }
    });

    $('.export-one-time-service').on('click', function () {
        if ($("input[name=from]").val() != "" && $("input[name=to]").val() != "") {
            window.location.href = Routing.generate('client_order_export_one_time_service', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
            $('.export-one-time-service').removeClass('disabled');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
            $('.export-one-time-service').addClass('disabled');
        }
    });

    $('.export-trade-service').on('click', function () {
        if ($("input[name=from]").val() != "" && $("input[name=to]").val() != "") {
            window.location.href = Routing.generate('client_order_export_purchase_trade_service', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
            $('.export-trade-service').removeClass('disabled');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
            $('.export-trade-service').addClass('disabled');
        }
    });
});