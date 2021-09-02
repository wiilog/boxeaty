import {$document} from "../app";
import $ from "jquery";
import Modal, {clearForm, processForm} from "../modal";
import "../styles/pages/planning.scss";
import AJAX from "../ajax";
import {findCoordinates, Map} from "../maps";
import Flash from "../flash";
import Sortable from "../sortable";

$document.ready(() => {
    const $filters = $(`.filters`);

    $filters.find(`.filter`).click(function() {
        reloadPlanning();
    });

    initializePlanning();

    $(`.new-delivery-round`).click(() => {
        const params = processForm($filters).asObject();

        if(params.from && params.to) {
            const ajax = AJAX.route(`POST`, `planning_delivery_round_template`, params);

            Modal.load(ajax, {
                processor: processSortables,
                afterOpen: modal => {
                    const map = Map.create(`delivery-round-map`);

                    setupDeliveryRoundSortables(map);

                    modal.element.find(`[name="method"]`).on(`change`, function() {
                        const value = Number($(this).val());

                        if(value) {
                            for(const element of modal.element.find(`.order[data-id]`)) {
                                const $element = $(element);
                                if($element.data(`delivery-method`) === value) {
                                    $element.removeClass(`d-none`);
                                } else {
                                    if($element.closest(`.assigned-deliveries`).exists()) {
                                        $element.detach();
                                        modal.element.find(`.available-deliveries`).append($element);
                                    }

                                    $element.addClass(`d-none`);
                                }
                            }
                        } else {
                            modal.element.find(`.order[data-id]`).removeClass(`d-none`);
                        }
                    });

                    modal.element.find(`[name="cost"], [name="distance"]`).on(`keyup`, function() {
                        updateAverage($(this));
                    });
                },
                success: () => reloadPlanning(),
            });
        } else {
            Flash.add(`warning`, `Les dates sont obligatoires pour affecter une tournée`);
        }
    });

    $(`.start-delivery`).click(() => {
        const params = processForm($filters).asObject();
        const ajax = AJAX.route(`POST`, `planning_delivery_initialize_template`, params);

        Modal.load(ajax, {
            processor: processSortables,
            afterOpen: modal => {
                if(params.from && params.to && params.depository) {
                    loadDeliveryLaunching(modal);
                }

                modal.element.find(`.data`).on(`change`, function() {
                    loadDeliveryLaunching(modal);
                })
            },
            submitter: (modal, $button) => {
                const data = processForm(modal.element, $button);

                const from = data.get('from'); // TODO romain utiliser ?
                const to = data.get('to'); // TODO romain utiliser ?
                const depository = data.get('depository');

                const $ordersToStartContainer = modal.element.find('.orders-to-start');
                const $ordersToStart = $ordersToStartContainer.find('.order');
                if($ordersToStart.exists()) {
                    const assignedForStart = $ordersToStart
                        .map((_, order) => $(order).data('id'))
                        .toArray();
                    if(!isStockValid(modal)) {
                        return checkStock(modal, {
                            depository,
                            assignedForStart
                        });
                    } else {
                        const params = {
                            from: from,
                            to: to,
                            depository: depository,
                            assignedForStart
                        };

                        return AJAX.route(`POST`, `planning_delivery_launch`, params)
                            .json()
                            .then(() => {
                                reloadPlanning();
                                modal.close();
                            });
                    }
                }
            }
        });
    });


    $(document).on('click', `.validate`, function() {
        const ajax = AJAX.route(`POST`, `planning_delivery_validate_template`, {
            order: $(this).closest(`.order`).data(`id`)
        });

        Modal.load(ajax, {
            success: () => reloadPlanning()
        });
    });

    $(document).on(`click`, `.late-order-send-mail`, function() {
        AJAX.route(`POST`, `planning_send_late_order_mail`, {
            order: $(this).closest(`.order`).data(`id`)
        }).json();
    });

    $(`.empty-filters`).click(function() {
        clearForm($(this).parents(`.filters`))
    });
});

function initializePlanning() {
    const sortables = Sortable.create(`.column-content`, {
        acceptFrom: `.column-content`,
    });

    for(const column of sortables) {
        column.addEventListener(`sortupdate`, e => changePlannedDate(e.detail));
    }
}

function updateAverage($element) {
    const count = $(`.assigned-deliveries .order[data-id]`).length;
    if(count) {
        $element.siblings(`.data-divided`).val(Math.round($element.val() / count * 100) / 100);
    }
}

function setupDeliveryRoundSortables(map) {
    Sortable.create(`.available-deliveries`, {
        acceptFrom: `.deliveries`,
        orientation: `horizontal`,
    });

    Sortable.create(`.assigned-deliveries`, {
        acceptFrom: `.deliveries`,
        orientation: `horizontal`,
    });

    $(`.available-deliveries, .assigned-deliveries`).on(`sortupdate`, async function() {
        updateAverage($(`[name="cost"]`));
        updateAverage($(`[name="distance"]`));

        const locations = [];
        for(const element of $(`.assigned-deliveries`).find(`.order[data-id]`)) {
            const address = $(element).data(`address`);
            const coordinates = await findCoordinates(address);

            if(coordinates[0]) {
                locations.push({
                    title: address,
                    latitude: coordinates[0].lat,
                    longitude: coordinates[0].lon,
                });
            }
        }

        let i = 1;
        $(`.assigned-deliveries .order`).map(function() {
            $(this).find(`.number`).text(`${i}.`);
            i += 1;
        });

        map.setMarkers(locations);
    });
}

function processSortables(data, errors, modal) {
    const $modal = modal.element;
    const assigned = $modal.find(`.assigned-deliveries .order[data-id]:not(.d-none)`).map(function() {
        return $(this).data(`id`);
    });
    const ready = $modal.find(`.orders-to-start .order[data-id]:not(.d-none)`).map(function() {
        return $(this).data(`id`);
    });

    data.append(`assignedForRound`, assigned.toArray());
    data.append(`assignedForStart`, ready.toArray());
}

async function changePlannedDate(detail) {
    Sortable.create(`.column-content`, `disable`);
    const $item = $(detail.item);
    const $destination = $(detail.destination.container).parent();

    const result = await AJAX.route(`POST`, `planning_change_date`, {
        order: $item.data(`id`),
    }).json($destination.data(`date`));

    $item.replaceWith(result.card);

    Sortable.create(`.column-content`, `enable`);
}

function reloadPlanning() {
    const $filters = $('.filters');
    const params = processForm($filters).asObject();

    AJAX.route(`GET`, `planning_content`, params)
        .json()
        .then(data => {
            $(`#planning`).html(data.planning);
            initializePlanning();
        });
}

function checkStock(modal, data) {
    return AJAX
        .route(`POST`, `planning_delivery_start_check_stock`, data)
        .json()
        .then((res) => {
            if(res.success) {
                const $quantitiesInformationContainer = modal.element.find('.quantities-information-container');
                const $quantitiesInformation = $quantitiesInformationContainer.find('.quantities-information');
                const $orderToStartContainer = modal.element.find('.orders-to-start');
                const $allOrdersContainer = modal.element.find('.available-order-to-start, .orders-to-start');

                $allOrdersContainer.find('.order')
                    .removeClass('available')
                    .removeClass('unavailable');
                for(const unavailableOrder of res.unavailableOrders) {
                    $orderToStartContainer.find(`.order[data-id="${unavailableOrder}"]`).addClass('unavailable');
                }
                $orderToStartContainer.find('.order:not(.unavailable)').addClass('available');
                $quantitiesInformation.empty();

                const quantityErrors = res.availableBoxTypeData.filter((boxTypeData) => (
                    boxTypeData.orderedQuantity > boxTypeData.availableQuantity
                ));

                if(quantityErrors.length > 0) {
                    $quantitiesInformationContainer.removeClass('d-none');
                } else {
                    $quantitiesInformationContainer.addClass('d-none');
                }

                for(const boxTypeData of quantityErrors) {
                    $quantitiesInformation.append(`
                    <label class="ml-2">
                        <strong>${boxTypeData.name}</strong> : Quantité commandée <strong>${boxTypeData.orderedQuantity}</strong> - disponible en stock <strong>${boxTypeData.availableQuantity}</strong> en propriété <strong>${boxTypeData.client}</strong>
                    </label>
                `);
                }

                updateSubmitButtonLabel(modal);
            }
        });

}

function onOrdersDragAndDropDone(modal) {
    updateSubmitButtonLabel(modal);
}

function isStockValid(modal) {
    return modal.element.find(`.orders-to-start .order`).exists() &&
        modal.element.find(`.orders-to-start .order.available`).count() ===
        modal.element.find(`.orders-to-start .order`).count();
}


function updateSubmitButtonLabel(modal) {
    const $ordersToStart = modal.element.find('.orders-to-start .order');
    const $submitButton = modal.element.find('.submit-button');

    $submitButton.attr(`disabled`, !$ordersToStart.exists());
    if($ordersToStart.exists() && isStockValid(modal)) {
        $submitButton.text("Valider le lancement");
    } else {
        $submitButton.text("Vérifier le stock");
    }
}

function loadDeliveryLaunching(modal) {
    const params = processForm(modal.element.find(`.delivery-launching-filters`)).asObject();
    AJAX.route(`POST`, `planning_delivery_launching_filter`, params)
        .json()
        .then((response) => {
            modal.element.find('.deliveries-container').empty()
            modal.element.find('.deliveries-container').addClass('d-none');

            if(response.success) {
                modal.element.find('.deliveries-container').removeClass('d-none');
                modal.element.find('.deliveries-container').append(response.template);
                const sortables = Sortable.create(`#modal-start-delivery .deliveries`, {
                    acceptFrom: `#modal-start-delivery .deliveries`,
                });
                $(sortables).on('sortupdate', () => {
                    onOrdersDragAndDropDone(modal);
                })
            }
        });
}
