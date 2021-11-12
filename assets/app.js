import './icons.js';

import './styles/app.scss';

import {Modal as BootstrapModal} from 'bootstrap';
import 'arrive';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';

import $ from 'jquery';
import Quill from 'quill/dist/quill.js';
import Toolbar from 'quill/modules/toolbar';
import Snow from 'quill/themes/snow';
import Routing from '../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import {createPopper} from '@popperjs/core';
import Leaflet from "leaflet";
import Chart from "chart.js/auto";

import './util';
import './pages/security';
import './select2';
import './jquery';
import AJAX from "./ajax";
import Modal from "./modal";

export const $document = $(document);

const routes = require(`../public/generated/routes.json`);
Routing.setRoutingData(routes);
global.Routing = Routing;

Leaflet.Icon.Default.imagePath = '/build/vendor/leaflet/images/';
global.Chart = Chart;
Chart.defaults.plugins.legend.display = true;
Chart.defaults.plugins.legend.position = "right";

// make all modals static
BootstrapModal.Default.backdrop = `static`;

$document.ready(() => $('.show-onload').modal("show"));
//tooltips
$document.ready(() => $('[data-toggle="tooltip"]').tooltip())
    .arrive(`[data-toggle="tooltip"]`, function() {
        $(this).tooltip()
    });

//activate dropdowns
$(`.datatable-action`).click(() => $(`.datatable-action-dropdown`).toggle());
$(`.display-menu`).click(() => $(`#menu-dropdown`).mobileSlideToggle());
$(`.menu-container`)
    .on(`click`, `.category`, function(e) {
        if($(e.target).closest(`.category-dropdown`).exists()) {
            return
        }

        const $dropdown = $(this).children(`.category-dropdown`);
        const wasVisible = $dropdown.is(`:visible`);

        $(`.category-dropdown`).mobileSlideUp();
        if(!wasVisible) {
            $dropdown.mobileSlideDown();
        }
    })
    .on(`click`, `.close-menu`, () => {
        $(`#menu-dropdown`).mobileSlideUp()
    });

//remove the menu when clicking outside
$document.click(e => {
    const selector = `[data-toggle="dropdown"], .dropdown-menu, .display-menu, #menu-dropdown`;
    const $target = $(e.target);

    if(!$target.closest(selector).exists() && !$target.is(selector)) {
        $(`#menu-dropdown, .category-dropdown, .dropdown-menu`).hide();
    }

    if($target.hasClass('display-profile')) {
        $('#menu-dropdown').hide();
    } else if($target.hasClass('display-menu')) {
        $('.dropdown-menu').hide();
    }
});

Quill.register({
    'modules/toolbar.js': Toolbar,
    'themes/snow.js': Snow,
});

const QUILL_CONFIG = {
    modules: {
        toolbar: [
            [{header: [1, 2, 3, false]}],
            [`bold`, `italic`, `underline`, `image`],
            [{list: `ordered`}, {list: `bullet`}]
        ]
    },
    formats: [
        `header`,
        `bold`, `italic`, `underline`, `strike`, `blockquote`,
        `list`, `bullet`, `indent`, `link`, `image`
    ],
    theme: `snow`,
};

$document.ready(() => $(`[data-wysiwyg]`).each(initializeWYSIWYG))
    .arrive(`[data-wysiwyg]`, initializeWYSIWYG);

function initializeWYSIWYG() {
    new Quill(this, QUILL_CONFIG);
}

$document.ready(() => $(`[data-toggle="dropdown"]`).each(initializeDropdown))
    .arrive(`[data-toggle="dropdown"]`, initializeDropdown);

function initializeDropdown() {
    const $button = $(this);
    const $dropdown = $button.siblings(`.dropdown-menu`);

    $button.click(function() {
        if($dropdown.is(`:visible`)) {
            $dropdown.hide();
        } else {
            $(`.dropdown-menu`).hide();
            $dropdown.show();
        }

        createPopper($button[0], $dropdown[0], {
            placement: $button.data(`placement`) || `left`,
        });
    })
}

//click on own username to edit
$document.ready(() => {
    const $currentUser = $(`#current-user`);

    if($currentUser.exists()) {
        $(`.current-user`).click(() => {
            const ajax = AJAX.route(`POST`, `user_edit_template`, {
                user: $currentUser.val(),
            });
            $(document).on(`click`, `button.change-password`, function() {
                $(this).parents(`.modal`).find(`div.change-password`).slideToggle();
            });

            Modal.load(ajax);
        });
    }
});
