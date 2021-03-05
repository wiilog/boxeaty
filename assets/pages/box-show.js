import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Flash from "../flash";
import "../styles/pages/box-show.scss";

$(document).ready(() => {
    getBoxTrackingMovements();
});

function getBoxTrackingMovements(start = 0) {
    const $historyWrapper = $('.history-wrapper');
    const $showMoreWrapper = $historyWrapper.find('.show-more-wrapper');
    const $showMoreButton = $showMoreWrapper.find('button');
    $showMoreButton.pushLoader();

    AJAX.route('GET', 'get_box_mouvements', {box: $('#box-id').val(), start})
        .json((result) => {
            if(result.success) {
                const data = (result.data || []);
                const historyLines = data.map(({state, color, comment, date}) => `
                    <div class="timeline-line">
                        <span class="timeline-line-marker"><strong>${date}</strong></span>
                        <span class="timeline-line-title ml-3">${state}</span>
                        <div class="timeline-line-comment alert alert-${color}">${comment || 'Aucun commentaire'}</div>
                    </div>
                `);
                $historyWrapper.append(...historyLines);

                $showMoreWrapper.remove();
                if(!result.isTail && data.length > 0) {
                    $historyWrapper.append($(`<div/>`, {
                        class: "d-flex justify-content-center show-more-wrapper",
                        html: $(`<button/>`, {
                            class: "secondary",
                            type: "button",
                            text: "Voir plus",
                            click: () => getBoxTrackingMovements(data.length)
                        })
                    }));
                }
            } else {
                Flash.add('danger', `L'historique de la Box n'a pas pu être récupéré`);
                $showMoreWrapper.remove();
            }
        })
}