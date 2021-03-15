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

        $element.select2({
            placeholder: $element.data(`placeholder`),
            allowClear: true,
            tags: $element.is(`[data-editable]`),
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
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))));
$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
$(document).arrive('.select2-search--dropdown .select2-search__field', function() {
    //fixes select2 search focus bug
    setTimeout(() => { $(this).focus(); }, 200);
});
