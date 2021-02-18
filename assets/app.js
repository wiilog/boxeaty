import './styles/app.scss';

import 'bootstrap';
import 'arrive';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';
import 'select2';
import $ from 'jquery';
import Routing from '../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

import './select2';

global.$ = $;
global.Routing = Routing;

const routes = require(`../public/generated/routes.json`);
Routing.setRoutingData(routes);

//activate dropdowns
$(`.display-menu`).click(() => $(`#menu-dropdown`).toggle());
$(`.datatable-action`).click(() => $(`.datatable-action-dropdown`).toggle());
$(`.category`).click((e) => {
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

export const SPINNER_WRAPPER_CLASS = `spinner-border-container`;
export const LOADING_CLASS = `loading`;

/**
 * Tests jQuery found an element
 *
 * @returns boolean
 */
jQuery.fn.exists = function () {
    return this.length !== 0;
}

jQuery.fn.load = function(callback, size = `small`) {
    const $element = $(this[0]); //the element on which the function was called

    $element.pushLoader(size);

    try {
        callback();
    } finally {
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

    if (!$element.find(`.${SPINNER_WRAPPER_CLASS}`).exists()) {
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
    if ($loaderWrapper.exists()) {
        $loaderWrapper.remove();
    }

    return this;
};
