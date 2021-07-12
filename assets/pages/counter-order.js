import {$document} from "../app";

import Modal from "../modal";
import AJAX, {GET} from "../ajax";
import $ from "jquery";
import Scan from "../scan";
import {SECONDS, String, Time} from "../util";

import "../styles/pages/order.scss";
import Flash from "../flash";

$document.ready(() => {
    $(`#scan-box`).click(() => openBoxesModal());
    $(`#scan-deposit-ticket`).click(() => openDepositTicketModal());
    $(`#new-counter-order`).click(() => window.location.href = Routing.generate(`home`, {
        redirection: '1'
    }));

    $document.arrive(`[data-manual]`, function() {
        $(this).on(`change`, _ => addInput(this, Number(this.value)));
    });

    $document.arrive(`[data-scan]`, function() {
        Scan.start(this, {
            loop: true,
            onScan: code => addInput(this, code)
        });
    });

    $document.on(`click`, `.delete-item`, function() {
        const $field = $(this);
        const $item = $field.closest(`.item`);
        const $totalPrice = $field.closest(`.modal`).find(`input[name="price"]`);
        const $inputDeleted = $item.find(`input`);
        const priceDeleted = Number($inputDeleted.data(`price`)) || 0;

        updatePriceInput($totalPrice, -priceDeleted);

        $item.remove();
    })
});

let lastScan = 0;

function addInput(element, code) {
    if(!code || lastScan + 3 > Time.now(SECONDS)) {
        return;
    }

    lastScan = Time.now(SECONDS);

    const $element = $(element);
    const $modal = $element.closest(`.modal`);
    const $container = $modal.find($element.data(`items`));

    const type = $element.data(`scan`) || $element.data(`manual`) || null;
    if(type !== `box` && type !== `ticket`) {
        console.error(`Type d'élément scannable inconnu "${type}"`);
        return;
    }

    if(Number.isInteger(code)) {
        code = $element.find(`option[value=${code}]`).text();
    }

    if(!$container.find(`input[value="${code}"]`).exists()) {
        const params = {
            type,
            number: code
        };

        AJAX.route(GET, `counter_order_info`, params).json(response => {
            if(!response.success) {
                return;
            }

            const $totalPrice = $modal.find(`input[name="price"]`);
            const modification = type === `box` ? response.price : -response.price;

            updatePriceInput($totalPrice, Math.abs(modification));

            $container.append(`
                <div class="item">
                    <input type="text" name="items" class="data-array mt-1" value="${code}" data-price="${modification}" readonly>
                    <span class="floating-icon delete-item">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
            `);
        });
    } else {
        if(type === `box`) {
            Flash.add(`danger`, `Cette Box a déjà été scannée`, true);
        } else {
            Flash.add(`danger`, `Ce ticket‑consigne a déjà été scanné`, true);
        }
    }

    if($element.is(`select`)) {
        $element.val(null).trigger(`change`);
    }
}

function openBoxesModal() {
    Modal.load(AJAX.route(`GET`, `counter_order_boxes_template`, {
        session: String.random(16),
    }));
}

function openDepositTicketModal() {
    Modal.load(AJAX.route(`GET`, `counter_order_deposit_tickets_template`, {
        session: String.random(16),
    }));
}

function updatePriceInput($input, delta) {
    const priceValue = Number($input.data('raw-value')) || 0;
    const newPriceValue = priceValue + delta;
    $input.data('raw-value', newPriceValue);
    $input.val(newPriceValue.toFixed(2).replace('.', ','));
}