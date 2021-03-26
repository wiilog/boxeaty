import {$document} from "../app";

import Modal from "../modal";
import AJAX, {GET} from "../ajax";
import $ from "jquery";
import Scan from "../scan";
import {randomString} from "../app";

import "../styles/pages/order.scss";

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

    $document.on(`click`, `.delete-item`, function() {
        const $field = $(this);
        const $item = $field.closest(`.item`);
        const $totalPrice = $field.closest(`.modal`).find(`input[name="price"]`);

        $totalPrice.val(Number($totalPrice.val()) - Number($item.find(`input`).data(`price`)));
        $item.remove();
    })
});

function addInput(element, code) {
    if(!code) {
        return;
    }

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

        AJAX.route(GET, `order_info`, params).json(response => {
            const $totalPrice = $modal.find(`input[name="price"]`);
            const modification = type === `box` ? response.price : -response.price;

            $totalPrice.val(Number($totalPrice.val()) + modification);
            $container.append(`
                <div class="item">
                    <input type="text" name="items" class="data data-array mt-1" value="${code}" data-price="${modification}" readonly>
                    <span class="floating-icon delete-item">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
            `);
        });
    } else {
        if(type === `box`) {
            Flash.add(`danger`, `Cette Box a déjà été scannée`);
        } else {
            Flash.add(`danger`, `Ce ticket-consigne a déjà été scanné`);
        }
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
