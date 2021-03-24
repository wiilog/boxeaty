import $ from "jquery";

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

jQuery.fn.keymap = function(callable, grouped = false) {
    const values = {};
    for(const input of this) {
        const [key, value] = callable(input);

        if(grouped) {
            if(values[key] === undefined) {
                values[key] = [];
            }

            values[key].push(value);
        } else {
            values[key] = value;
        }
    }

    return values;
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
