import {$document} from "../app";

import $ from "jquery";
import Modal, {clearForm, processForm} from "../modal";
import sortable from '../../node_modules/html5sortable/dist/html5sortable.es.js';
import "../styles/pages/planning.scss";
import AJAX from "../ajax";
import {findCoordinates, Map} from "../maps";
import {DateTools} from "../util";
import Flash from "../flash";

$(document).ready(() => {
    const $filters = $(`.filters`);

    DateTools.manageDateLimits(`input[name=from]`, `input[name=to]`, 20);

    $filters.find(`.filter`).click(function () {
        const params = processForm($filters).asObject();

        AJAX.route(`GET`, `planning_content`, params)
            .json()
            .then(data => {
                $(`#planning`).html(data.planning);
                initializePlanning();
            });
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

                    modal.element.find(`[name="method"]`).on(`change`, function () {
                        const value = Number($(this).val());

                        if (value) {
                            for (const element of modal.element.find(`.order[data-id]`)) {
                                const $element = $(element);
                                if ($element.data(`delivery-method`) === value) {
                                    $element.removeClass(`d-none`);
                                } else {
                                    $element.addClass(`d-none`);
                                }
                            }
                        } else {
                            modal.element.find(`.order[data-id]`).removeClass(`d-none`);
                        }
                    });

                    modal.element.find(`[name="cost"], [name="distance"]`).on(`keyup`, function () {
                        updateAverage($(this));
                    });
                },
            });
        } else {
            Flash.add(`warning`, `Les dates sont obligatoires pour affecter une tournée`);
        }
    });

    $(`.empty-filters`).click(function() {
        clearForm($(this).parents(`.filters`))
    });

    $document.arrive(`[data-sortable]`, function () {
        const $this = $(this);

        sortable(this, {
            acceptFrom: $this.data(`accept-from`),
        });
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
    if (count) {
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

    assigned.addEventListener(`sortupdate`, async function () {
        updateAverage($(`[name="cost"]`));
        updateAverage($(`[name="distance"]`));

        const locations = [];
        for (const element of $(assigned).find(`.order[data-id]`)) {
            const address = $(element).data(`address`);
            const coordinates = await findCoordinates(address);

            locations.push({
                title: address,
                latitude: coordinates[0].lat,
                longitude: coordinates[0].lon,
            });
        }

        if (locations.length) {
            map.setMarkers(locations)
        }
    });
}

function processSortables(data, errors, modal) {
    const $modal = modal.element;
    const assigned = $modal.find(`.assigned-deliveries .order[data-id]:not(.d-none)`).map(function () {
        return $(this).data(`id`);
    });

    data.append(`assigned`, assigned.toArray());
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
