import $ from "jquery";
import "arrive";
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

import "../jquery";
import "../select2";

const routes = require(`../../public/generated/routes.json`);
Routing.setRoutingData(routes);
global.Routing = Routing;

//password toggle eye icon
$(document).on(`click`, `.show-password span`, function() {
    const $input = $(this).parents(`label`).find(`input`);

    if($input.attr(`type`) === `password`) {
        $input.attr(`type`, `text`);
    } else {
        $input.attr(`type`, `password`);
    }
})
