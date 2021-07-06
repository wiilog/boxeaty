import $, {GROUP_WHEN_NEEDED} from 'jquery';
import 'select2';
import 'select2/src/js/select2/i18n/fr';

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
    orderBox: `ajax_select_counter_order_boxes`,
    orderDepositTicket: `ajax_select_counter_order_deposit_tickets`,
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

        $element.removeAttr(`data-s2`);
        $element.attr(`data-s2-initialized`, ``);
        $element.wrap(`<div style="position: relative"/>`);

        const config = {};
        if(type) {
            if(!ROUTES[type]) {
                console.error(`No select route found for ${type}`);
            }

            config.ajax = {
                url: Routing.generate(ROUTES[type]),
                data: params => Select2.includeParams($element, params),
                dataType: `json`
            };
        }

        if(type && !INSTANT_SELECT_TYPES[type]) {
            config.minimumInputLength = 1;
        }

        $element.select2({
            placeholder: $element.data(`placeholder`),
            tags: $element.is('[data-editable]'),
            allowClear: !$element.is(`[multiple]`),
            dropdownParent: $element.parent(),
            language: {
                errorLoading: () => `Une erreur est survenue`,
                inputTooShort: args => `Saisissez au moins ${args.minimum - args.input.length} caractère`,
                noResults: () => `Aucun résultat`,
                searching: () => null,
                removeItem: () => `Supprimer l'élément`,
                removeAllItems: () => `Supprimer tous les éléments`,
            },
            ...config,
        });

        $element.on('select2:open', function(e) {
            const evt = "scroll.select2";
            $(e.target).parents().off(evt);
            $(window).off(evt);

            // hide all other select2 dropdown
            $('[data-s2-initialized]').each(function() {
                const $select2 = $(this);
                if(!$select2.is($element)) {
                    $select2.select2('close');
                }
            });

            const $select2Parent = $element.parent();
            const $searchField = $select2Parent.find('.select2-search--dropdown .select2-search__field');
            if($searchField.exists()) {
                setTimeout(() => $searchField[0].focus(), 300);
            }
        });
    }

    static includeParams($element, params) {
        if($element.is(`[data-include-params]`)) {
            const selector = $element.data(`include-params`);
            const closest = $element.data(`[data-include-params-parent]`) || `.modal`;
            const $fields = $element
                    .closest(closest)
                    .find(selector);

            const values = $fields
                .filter((_, elem) => elem.name && elem.value)
                .keymap((elem) => [elem.name, elem.value], GROUP_WHEN_NEEDED);

            params = {
                ...params,
                ...values,
            };
        }

        return params;
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))));
$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
