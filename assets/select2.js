import $ from 'jquery';
import 'select2';

const ROUTES = {
    box: `ajax_select_boxes`,
    group: `ajax_select_groups`,
    client: `ajax_select_clients`,
    multiSite: `ajax_select_multi_sites`,
    user: `ajax_select_users`,
    location: `ajax_select_locations`,
    type: `ajax_select_type`,
    quality: `ajax_select_quality`,
}

class Select2 {
    static init($element) {
        const type = $element.data(`s2`);

        if(!$element.find(`option[selected]`).exists() && !type && !$element.is(`[data-no-empty-option]`)) {
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

        $element.select2({
            placeholder: $element.data(`placeholder`),
            language: {
                noResults: () => `Aucun résultat`,
                searching: () => null,
            },
            ...config,
        });
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))))
    .arrive(`[data-s2]`, function() {
        Select2.init($(this));
    });
