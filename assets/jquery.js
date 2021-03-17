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

jQuery.fn.keymap = function(callable) {
    const values = {};
    for(const input of this) {
        const [key, value] = callable(input);
        values[key] = value;
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
