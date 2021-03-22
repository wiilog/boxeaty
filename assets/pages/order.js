import '../app';

import Modal from "../modal";
import AJAX from "../ajax";
import $ from "jquery";
import Scan from "../scan";
import {randomString} from "../app";

const $document = $(document);

$document.ready(() => {
    $(`#scan-box`).click(() => openBoxesModal());
    $(`#scan-deposit-ticket`).click(() => openDepositTicketModal());

    $document.arrive(`[data-manual]`, function() {
        $(this).on(`change`, _ => addInput(this, Number(this.value)));
    });

    $document.arrive(`[data-scan]`, function() {
        Scan.start(this, {
            loop: true,
            onScan: code => addInput(this, code)
        });
    });
});

function addInput(element, code) {
    if(!code) {
        return;
    }

    const $element = $(element);
    const $container = $element.closest(`.modal`).find($element.data(`items`));

    if(Number.isInteger(code)) {
        code = $element.find(`option[value=${code}]`).text();
    }

    if(!$container.find(`input[value="${code}"]`).exists()) {
        $container.append(`
            <input type="text" name="items" class="data data-array mt-1" value="${code}" readonly>
            <span class="floating-icon">
                <i class="fas fa-times"></i>
            </span>
        `);
    }

    if($element.is(`select`)) {
        $element.val(null).trigger(`change`);
    }
}

function openBoxesModal() {
    Modal.load(AJAX.route(`GET`, `order_boxes_template`, {
        session: randomString(16),
    }));
}

function openDepositTicketModal() {
    Modal.load(AJAX.route(`GET`, `order_deposit_tickets_template`, {
        session: randomString(16),
    }));
}
