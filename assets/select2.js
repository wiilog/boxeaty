import $ from 'jquery';
import 'select2';

const ROUTES = {
    box: `ajax_select_boxes`,
    client: `ajax_select_clients`,
}

export default class Select2 {
    static init($element) {
        if(!$element.find(`option`).exists() && !$element.is(`[data-no-empty-option]`)) {
            $element.prepend(`<option selected>`);
        }

        const type = $element.data(`s2`);
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
                searching: () => `Recherche en cours`,
            },
            ...config,
        });
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))))
    .arrive(`[data-s2]`, function() {
        Select2.init($(this));
    });
