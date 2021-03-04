import './styles/app.scss';

import 'bootstrap';
import 'arrive';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';
import '../node_modules/froala-editor/js/languages/fr.js'

import $ from 'jquery';
import FroalaEditor from 'froala-editor';
import Routing from '../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

import './select2';

global.$ = $;
global.Routing = Routing;

const routes = require(`../public/generated/routes.json`);
Routing.setRoutingData(routes);

//tooltips
$(document)
    .ready(() => $('[data-toggle="tooltip"]').tooltip())
    .arrive(`[data-toggle="tooltip"]`, function() {
        $(this).tooltip();
    });

//activate dropdowns
$(`.datatable-action`).click(() => $(`.datatable-action-dropdown`).toggle());
$(`.display-menu`).click(() => $(`#menu-dropdown`).toggle());
$(`.menu-container`).on(`click`, `.category`, (e) => {
    $(`.category-dropdown`).hide();
    $(e.currentTarget).children(`.category-dropdown`).toggle();
});

//remove the menu when clicking outside
$(document).click(e => {
    const $target = $(e.target);
    if(!$target.hasClass(`display-menu`) && !$target.closest(`#menu-dropdown`).exists() && $(`#menu-dropdown`).is(`:visible`)) {
        $(`#menu-dropdown, .category-dropdown`).hide();
    }
});

$(document).ready(initializeWYSIWYG)
    .arrive(`[data-wysiwyg]`, initializeWYSIWYG);

function bannerRemover() {
    $(this).remove();
}

const $document = $(document);
$document.ready(() => {
    $document.find(`.fr-wrapper div:not([class])`).each(bannerRemover);
    $document.arrive(`.fr-wrapper div:not([class])`, bannerRemover);
});

function initializeWYSIWYG() {
    new FroalaEditor(`[data-wysiwyg]`, {
        language: 'fr',
        placeholderText: 'Votre commentaire'
    });
}

export const SPINNER_WRAPPER_CLASS = `spinner-border-container`;
export const LOADING_CLASS = `loading`;

/**
 * Tests jQuery found an element
 *
 * @returns boolean
 */
jQuery.fn.exists = function() {
    return this.length !== 0;
}

jQuery.fn.load = function(callback, size = `small`) {
    const $element = $(this[0]); //the element on which the function was called

    $element.pushLoader(size);

    const result = callback();
    if(result !== undefined && result.finally) {
        result.finally(() => $element.popLoader())
    } else {
        $element.popLoader();
    }
};

/**
 * Add a loader to the element
 *
 * @returns {jQuery}
 */
jQuery.fn.pushLoader = function(size = `small`) {
    const $element = $(this[0]); //the element on which the function was called

    if(!$element.find(`.${SPINNER_WRAPPER_CLASS}`).exists()) {
        size = size === `small` ? `spinner-border-sm` : ``;

        $element.append(`<div class="spinner-border-container"><div class="spinner-border ${size}" role="status"></div></div>`);
        $element.addClass(LOADING_CLASS);
    }

    return this;
};

/**
 * Remove the loader from the element
 * @returns {jQuery}
 */
jQuery.fn.popLoader = function() {
    const $element = $(this[0]); //the element on which the function was called
    $element.removeClass(LOADING_CLASS);

    const $loaderWrapper = $element.find(`.${SPINNER_WRAPPER_CLASS}`)
    if($loaderWrapper.exists()) {
        $loaderWrapper.remove();
    }

    return this;
};
