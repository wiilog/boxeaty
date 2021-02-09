import './styles/app.scss';
import 'bootstrap';

import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';

import $ from 'jquery';
import Routing from '../public/bundles/fosjsrouting/js/router.min.js';

global.$ = $;
global.Routing = Routing;

import {DATATABLE_ACTIONS_TITLE, initDatatable} from './datatable';

const routes = require(`../public/generated/routes.json`);
Routing.setRoutingData(routes);

//activate dropdowns
$(`.display-menu`).click(() => $(`.menu-dropdown`).toggle());
$(`.category`).click((e) => {
    $(`.category-dropdown`).hide();
    $(e.currentTarget).children(`.category-dropdown`).toggle();
});

//remove the menu when clicking outside
$(document).click(e => {
    const $target = $(e.target);
    if(!$target.hasClass(`display-menu`) && !$target.closest(`.menu-dropdown`).length && $(`.menu-dropdown`).is(`:visible`)) {
        $(`.menu-dropdown, .category-dropdown`).hide();
    }
});

$(document).ready(() => {
    initDatatable("table-users", {
        ajax: {
            url: Routing.generate(`users_api`),
            method: "POST",
        },
        columns: [
            {data: "email", title: "Email"},
            {data: "last_login", title: "Dernière connexion"},
            {data: "role", title: "Rôle"},
            {data: "actions", title: DATATABLE_ACTIONS_TITLE},
        ],
    })
});
