import '../app';
import $ from "jquery";

$(document).ready(() => {
    const $from = $("input[name=from]");
    const $to = $("input[name=to]");

    $('.global-export').on('click', () => {
        window.location.href = Routing.generate('global_export');
    });

    $('.export-autonomous-management').on('click', function () {
        if ($from.val() !== "" && $to.val() !== "") {
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
        if ($from.val() !== "" && $to.val() !== "") {
            window.location.href = Routing.generate('client_order_export_one_time', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });

    $('.export-trade-service').on('click', function () {
        if ($from.val() !== "" && $to.val() !== "") {
            window.location.href = Routing.generate('client_order_export_purchase_trade', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });

    $('.export-recurrent-order').on('click', function () {
        if ($from.val() !== "" && $to.val() !== "") {
            window.location.href = Routing.generate('client_order_export_recurrent', {
                from: $("input[name=from]").val(),
                to: $("input[name=to]").val()
            });
            $('.error').text('');
        } else {
            $('.error').text('Veuillez renseigner une date de début et une date de fin');
        }
    });
});
