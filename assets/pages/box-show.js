import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Flash from "../flash";

import "../styles/pages/box-show.scss";

$(document).ready(() => {
    getBoxTrackingMovements();

    $('.comment-search').on('change', function () {
        getBoxTrackingMovements(0);
    });

    $('.history-wrapper').on('scroll', function () {
       if($(this).scrollTop() > 150) {
           $('.scroll-top').removeClass('d-none').fadeIn(500);
       } else {
           $('.scroll-top').fadeOut(500, function () {
               $(this).addClass('d-none');
           });
       }
    });

    $('.scroll-top-button').on('click', function () {
        $('.history-wrapper').animate({
            scrollTop: 0
        }, 800);
        return false;
    });
});

function getBoxTrackingMovements(start = 0) {
    const $historyWrapper = $('.history-wrapper');
    const $showMoreWrapper = $historyWrapper.find('.show-more-wrapper');
    const $showMoreButton = $showMoreWrapper.find('button');
    $showMoreButton.pushLoader();

    const search = $('.comment-search').val();

    AJAX.route('GET', 'get_box_mouvements', {box: $('#box-id').val(), search, start})
        .json((result) => {
            if(result.success) {
                if (start === 0) {
                    $('.history-wrapper').empty();
                }
                const data = (result.data || []);
                const historyLines = data.map(({state, color, comment, date}) => {
                    const $rawComment = $($.parseHTML(comment));
                    const trimmedComment = $rawComment.text().trim();

                    let $comment = `<div class="timeline-line-comment"></div>`;
                    if(trimmedComment.trim()) {
                        $comment = `<div class="timeline-line-comment alert alert-${color}">${comment}</div>`;
                    }

                    return `
                        <div class="timeline-line">
                            <span class="timeline-line-marker"><strong>${date}</strong></span>
                            <span class="timeline-line-title ml-3">${state}</span>
                            ${$comment}
                        </div>
                    `;
                });
                $historyWrapper.append(...historyLines);
                $historyWrapper.find(`[data-f-id="pbf"]`).remove()

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

                const customWrapperClass = 'd-flex justify-content-center align-items-center';
                if(data.length === 0) {
                    $historyWrapper.addClass(customWrapperClass);
                    $historyWrapper.append(`
                        <div class="d-flex flex-column align-items-center">
                            <i class="far fa-frown fa-3x"></i>
                            <p class="mt-2">Aucun commentaire ${search ? 'ne correspond à votre recherche' : ' pour cette box'}</p>
                        </div>
                    `);
                } else {
                    $historyWrapper.removeClass(customWrapperClass);
                }
            } else {
                Flash.add('danger', `L'historique de la Box n'a pas pu être récupéré`);
                $showMoreWrapper.remove();
            }
        })
}
