import $, {GROUP_WHEN_NEEDED} from 'jquery';
import 'select2';
import 'select2/src/js/select2/i18n/fr';

const ROUTES = {
    box: `ajax_select_boxes`,
    orderStatus: 'ajax_select_order_status',
    orderType: 'ajax_select_order_type',
    depository: `ajax_select_depositories`,
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
    deliverer: `ajax_select_deliverers`,
    deliveryMethod: `ajax_select_delivery_methods`,
};

const INSTANT_SELECT_TYPES = {
    type: true,
    orderType: true,
    orderStatus: true,
    quality: true,
    group: true,
    depository: true,
    deliveryMethod: true,
};

export default class Select2 {
    static init($element) {
        const type = $element.data(`s2`);
        const icon = $element.is(`[data-icon]`);
        const disableSearch = $element.is(`[data-no-searching]`);
        const classes =  $element.attr('class');

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

        if (icon) {
            config.templateResult = format;
            config.templateSelection = format;
        }

        if (disableSearch) {
            config.minimumResultsForSearch = -1;
        }

        if(type && !INSTANT_SELECT_TYPES[type]) {
            config.minimumInputLength = 1;
        }

        $element.select2({
            placeholder: $element.data(`placeholder`),
            tags: $element.is('[data-editable]'),
            allowClear: !$element.is(`[data-no-empty-option]` || !$element.is(`[multiple]`)),
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
        $element.parent().find('.select2-container').addClass(classes);
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

function format(state) {
    return $(`<i class="bxi bxi-${state.id}"></i>`);
}