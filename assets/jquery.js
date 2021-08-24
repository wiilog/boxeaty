import $ from "jquery";
import Flash from "./flash";

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

/**
 * Count how many jQuery elements were found
 *
 * @returns number
 */
jQuery.fn.count = function() {
    return this.length;
}

export const NO_GROUPING = 0;
export const GROUP_EVERYTHING = 0;
export const GROUP_WHEN_NEEDED = 0;

jQuery.fn.keymap = function(callable, grouping = NO_GROUPING) {
    const values = {};
    for(const input of this) {
        const [key, value] = callable(input);

        if(grouping === NO_GROUPING) {
            values[key] = value;
        } else if(grouping === GROUP_EVERYTHING) {
            if(!values[key]) {
                values[key] = [];
            }

            values[key].push(value);
        } else if(grouping === GROUP_WHEN_NEEDED) {
            if(values[key] === undefined) {
                values[key] = {__single_value: value};
            } else if(values[key].__single_value !== undefined) {
                values[key] = [values[key].__single_value, value];
            } else {
                values[key].push(value);
            }
        }
    }

    if(grouping === GROUP_WHEN_NEEDED) {
        for(const[key, value] of Object.entries(values)) {
            values[key] = value.__single_value !== undefined ? value.__single_value : value;
        }
    }

    return values;
}

jQuery.fn.load = function(callback, size = `small`) {
    const $element = $(this[0]); //the element on which the function was called

    if($element.hasClass(LOADING_CLASS)) {
        Flash.add(Flash.WARNING, `Opération en cours d'exécution`, true);
    } else {
        $element.pushLoader(size);

        const result = callback();
        if (result !== undefined && result.finally) {
            result.finally(() => $element.popLoader())
        } else {
            $element.popLoader();
        }
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

jQuery.fn.mobileSlideToggle = function() {
    if(window.screen.width <= 768) {
        $(this).slideToggle();
    } else {
        $(this).toggle();
    }
}

jQuery.fn.mobileSlideUp = function() {
    if(window.screen.width <= 768) {
        $(this).slideUp();
    } else {
        $(this).hide();
    }
}

jQuery.fn.mobileSlideDown = function() {
    if(window.screen.width <= 768) {
        $(this).slideDown();
    } else {
        $(this).show();
    }
}
