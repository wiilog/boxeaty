import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import Flash from "../flash";

import "../styles/pages/box-show.scss";
import Modal from "../modal";

$(document).ready(() => {
    getBoxTrackingMovements();
    getBoxInCrate();

    $(`.edit-box`).click(() => {
        const ajax = AJAX.route(`POST`, `box_edit_template`, {
            box: $('#box-id').val()
        });
        Modal.load(ajax, {
            success : () =>{
                window.location.href = Routing.generate(`box_show`, {
                    box: $('#box-id').val()
                });
            }
        })
    });

    $(`.delete-box`).click(() => {
        const ajax = AJAX.route(`POST`, `box_delete_template`, {
            box: $('#box-id').val()
        });
        Modal.load(ajax, {
            success : () =>{
                window.location.href = Routing.generate(`boxes_list`);
            }
        })
    });

    $(document).arrive('.delete-box-in-crate', function() {
        $(this).click(function() {
            const ajax = AJAX.route(`POST`, `box_delete_in_crate_template`, {
                box: $(this).data('id'),
                crate: $('#box-id').val()
            });
            Modal.load(ajax, {
                success: (response) => {
                    $('.refresh-after-add').replaceWith(response.template);
                }});
            });
    })

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

    $('.add-box-to-cart').on(`change`, function (){
        const box = $(this).val();

        const ajax = AJAX.route(`GET`, `add_box_in_crate`, {
            box: box,
            crate: $('#box-id').val()
        });
        ajax.json()
            .then(response =>{
                $('.refresh-after-add').replaceWith(response.template);
            });
        $(this).empty();
    });
});

function getBoxTrackingMovements(start = 0) {
    const $historyWrapper = $('.history-wrapper');
    const $showMoreWrapper = $historyWrapper.find('.show-more-wrapper');
    const $showMoreButton = $showMoreWrapper.find('button');
    $showMoreButton.pushLoader();

    const search = $('.comment-search').val();

    AJAX.route('GET', 'get_box_mouvements', {box: $('#box-id').val(), search, start})
        .json()
        .then((result) => {
            if(result.success) {
                if (start === 0) {
                    $('.history-wrapper').empty();
                }
                const data = (result.data || []);
                const historyLines = data.map(({state, crate, quality, date, time, operator, location, depository}) => {
                    const $rawQuality = $($.parseHTML(quality));
                    const trimmedQuality = $rawQuality.text().trim();

                    let $quality = `<div class="timeline-line-comment"></div>`;
                    if(trimmedQuality.trim()) {
                        $quality = `<div class="timeline-line-comment">${quality}</div>`;
                    }

                    let subtitle;
                    if(operator){
                        subtitle = `Opérateur : ${operator}`;
                    }
                    if (depository) {
                        subtitle = subtitle ? `${subtitle} - ` : '';
                        subtitle += depository;
                    }
                    if (location) {
                        subtitle = subtitle ? `${subtitle} - ` : '';
                        subtitle += location;
                    }

                    if (crate) {
                        state += ` dans la caisse <a href="${Routing.generate('box_show', {box: crate.id})}">${crate.number}</a>`;
                    }

                    return `
                        <div class="timeline-line d-flex">
                            <span class="timeline-line-marker"><strong>${date}</strong><p>${time}</p></span>
                            <div class="timeline-line-title ml-3">
                                <div class="d-flex"><strong>${state}</strong>${$quality}</div>
                                <p>${subtitle}</p>
                            </div>
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

function getBoxInCrate() {
    const ajax = AJAX.route(`GET`, `box_in_crate_api`, {
        id: $('#box-id').val()
    });
    ajax.json()
        .then(response =>{
            $('.refresh-after-add').replaceWith(response.template);
        });
}
