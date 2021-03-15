import $ from 'jquery';
import 'select2';

const ROUTES = {
    box: `ajax_select_boxes`,
    group: `ajax_select_groups`,
    client: `ajax_select_clients`,
    multiSite: `ajax_select_multi_sites`,
    user: `ajax_select_users`,
    anyLocation: `ajax_select_any_location`,
    kiosk: `ajax_select_kiosks`,
    location: `ajax_select_locations`,
    type: `ajax_select_type`,
    quality: `ajax_select_quality`,
    availableBox: `ajax_select_available_boxes`,
    depositTicket: `ajax_select_deposit_tickets`,
}

const INSTANT_SELECT_TYPES = {
    type: true,
    quality: true,
    group: true,
}

export default class Select2 {
    static init($element) {
        const type = $element.data(`s2`);
        if(!$element.find(`option[selected]`).exists() && !type &&
            !$element.is(`[data-no-empty-option]`) && !$element.is(`[data-editable]`)) {
            $element.prepend(`<option selected>`);
        }

        const config = {};
        if(type) {
            if(!ROUTES[type]) {
                console.error(`No select route found for ${type}`);
            }

            config.ajax = {
                url: Routing.generate(ROUTES[type]),
                dataType: `json`
            };
        }

        if (type && !INSTANT_SELECT_TYPES[type]) {
            config.minimumInputLength = 1;
        }

        const $clonedElement = $element.clone();
        $clonedElement.removeAttr('data-s2');
        $clonedElement.attr('data-s2-init', '');
        const $selectParent = $('<div/>', {
            html: $clonedElement
        });
        $element.replaceWith($selectParent);

        $clonedElement.select2({
            placeholder: $clonedElement.data(`placeholder`),
            tags: $clonedElement.is('[data-s2-tags]'),
            allowClear: $clonedElement.is(`[multiple]`),
            dropdownParent: $selectParent,
            language: {
                inputTooShort: () => 'Veuillez entrer au moins 1 caractère.',
                noResults: () => `Aucun résultat`,
                searching: () => null,
            },
            ...config,
        });

        if($element.is(`[multiple]`)) {
            $element.siblings(`.select2-container`).addClass(`multiple`);
        }

        $clonedElement.on('select2:open', function (e) {
            const evt = "scroll.select2";
            $(e.target).parents().off(evt);
            $(window).off(evt);
            // we hide all other select2 dropdown
            $('[data-s2-init]').each(function () {
                const $select2 = $(this);
                if (!$select2.is($clonedElement)) {
                    $select2.select2('close');
                }
            })
        });
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))));
$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
