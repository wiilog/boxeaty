import {$document} from "../app";
import $ from "jquery";
import Modal, {clearForm, processForm} from "../modal";
import sortable from '../../node_modules/html5sortable/dist/html5sortable.es.js';
import "../styles/pages/planning.scss";
import AJAX from "../ajax";
import {findCoordinates, Map} from "../maps";
import {DateTools} from "../util";
import Flash from "../flash";

$document.ready(() => {
    const $filters = $(`.filters`);

    DateTools.manageDateLimits(`input[name=from]`, `input[name=to]`, 20);

    $filters.find(`.filter`).click(function() {
        const params = processForm($filters).asObject();
        reLoadPlanning(params);
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

                    setupSortables(map);

                    modal.element.find(`[name="method"]`).on(`change`, function() {
                        const value = Number($(this).val());

                        if(value) {
                            for(const element of modal.element.find(`.order[data-id]`)) {
                                const $element = $(element);
                                if($element.data(`delivery-method`) === value) {
                                    $element.removeClass(`d-none`);
                                } else {
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
                    if(!isStockValid(modal)) {
                        return checkStock(modal, {
                            depository,
                            assignedForStart: $ordersToStart
                                .map((_, order) => $(order).data('id'))
                                .toArray()
                        });
                    } else {
                        return AJAX.route(`POST`, `planning_delivery_launch`, {
                            from: from,
                            to: to,
                            depository: depository,
                            assignedForStart: $ordersToStart
                                .map((_, order) => $(order).data('id'))
                                .toArray()
                        })
                            .json()
                            .then(() => modal.close());
                    }
                }
            }
        });
    });


    $(document).on('click', `.validate`, function() {
        const ajax = AJAX.route(`POST`, `planning_delivery_validate_template`, {
            order: $(this).closest(`.order`).data("id")
        });

        Modal.load(ajax, {
            success: result => {
                const params = processForm($filters).asObject();
                reLoadPlanning(params);
            }
        });
    });

    $(`.empty-filters`).click(function() {
        clearForm($(this).parents(`.filters`))
    });
});

function initializePlanning() {
    const sortables = sortable(`.column-content`, {
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

function setupSortables(map) {
    const available = sortable(`.available-deliveries`, {
        acceptFrom: `.deliveries`,
    })[0];

    const assigned = sortable(`.assigned-deliveries`, {
        acceptFrom: `.deliveries`,
    })[0];

    assigned.addEventListener(`sortupdate`, async function() {
        updateAverage($(`[name="cost"]`));
        updateAverage($(`[name="distance"]`));

        const locations = [];
        for(const element of $(assigned).find(`.order[data-id]`)) {
            const address = $(element).data(`address`);
            const coordinates = await findCoordinates(address);

            locations.push({
                title: address,
                latitude: coordinates[0].lat,
                longitude: coordinates[0].lon,
            });
        }

        if(locations.length) {
            map.setMarkers(locations)
        }
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
    sortable(`.column-content`, `disable`);

    const $item = $(detail.item);
    const $destination = $(detail.destination.container).parent();

    const result = await AJAX.route(`POST`, `planning_change_date`, {
        order: $item.data(`id`),
    }).json($destination.data(`date`));

    $item.replaceWith(result.card);

    sortable(`.column-content`, `enable`);
}

function reLoadPlanning(params) {
    AJAX.route(`GET`, `planning_content`, params)
        .json()
        .then(data => {
            $(`#planning`).html(data.planning);
            initializePlanning();
        });
}

function checkStock(modal, data) {
    const $loading = modal.element.find('.modal-loading');
    $loading.addClass('d-flex')
        .removeClass('d-none');

    return AJAX
        .route(`POST`, `planning_delivery_start_check_stock`, data)
        .json()
        .then((res) => {
            const $quantitiesInformationContainer = modal.element.find('.quantities-information-container');
            const $quantitiesInformation = $quantitiesInformationContainer.find('.quantities-information');
            const $availableOrderToStartContainer = modal.element.find('.available-order-to-start');
            const $orderToStartContainer = modal.element.find('.orders-to-start');

            for(const unavailableOrder of res.unavailableOrders) {
                modal.element.find(`.orders-to-start .order[data-id="${unavailableOrder}"]`).addClass('unavailable');
            }
            $availableOrderToStartContainer.find('.order')
                .removeClass('available')
                .removeClass('unavailable');
            $orderToStartContainer.find('.order:not(.unavailable)').addClass('available');
            $orderToStartContainer.attr('data-stock-checked', 1);
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
                        Box Type ${boxTypeData.name} - Quantité commandée ${boxTypeData.orderedQuantity} - dispo en stock ${boxTypeData.availableQuantity} en propriété ${boxTypeData.client}
                    </label>
                `);
            }

            updateSubmitButtonLabel(modal);

            $loading
                .addClass('d-none')
                .removeClass('d-flex');

        });
}

function onOrdersDragAndDropDone(modal) {
    modal.element.find('.orders-to-start').attr('data-stock-checked', 0);
    updateSubmitButtonLabel(modal);
}

function isStockValid(modal) {
    return !modal.element.find('.orders-to-start')
        .map((_, element) => $(element))
        .map((_, $elem) => $elem.data(`stock-checked`) === `0` || $elem.find(`.order.unavailable`).exists())
        .filter(valid => !valid)
        .exists();
}


function updateSubmitButtonLabel(modal) {
    const $ordersToStartNotAvailable = modal.element.find('.orders-to-start .order:not(.available)');
    const $ordersToStart = modal.element.find('.orders-to-start .order');
    const $submitButton = modal.element.find('.submit-button');

    $submitButton.attr(`disabled`, !$ordersToStart.exists());

    if(!$ordersToStartNotAvailable.exists() && $ordersToStart.exists() && isStockValid(modal)) {
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
                const sortables = sortable(`.deliveries`, {
                    acceptFrom: `.deliveries`,
                });
                $(sortables).on('sortupdate', () => {
                    onOrdersDragAndDropDone(modal);
                })
            }
        });
}