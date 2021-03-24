import './styles/app.scss';

import 'bootstrap';
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

import './select2';
import './jquery';
import AJAX from "./ajax";
import Modal from "./modal";

global.$ = $;
global.Routing = Routing;

export const $document = $(document);

const routes = require(`../public/generated/routes.json`);
Routing.setRoutingData(routes);

// make all modals static
$.fn.modal.Constructor.Default.backdrop = `static`;

//tooltips
$(document)
    .ready(() => $('[data-toggle="tooltip"]').tooltip())
    .arrive(`[data-toggle="tooltip"]`, function() {
        $(this).tooltip();
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
$(document).click(e => {
    const selector = `[data-toggle="dropdown"], .dropdown-menu, .display-menu, #menu-dropdown`;
    const $target = $(e.target);

    if(!$target.closest(selector).exists() && !$target.is(selector)) {
        $(`#menu-dropdown, .category-dropdown, .dropdown-menu`).hide();
    }
});

$(document).ready(initializeWYSIWYG)
    .arrive(`[data-wysiwyg]`, initializeWYSIWYG);

Quill.register({
    'modules/toolbar.js': Toolbar,
    'themes/snow.js': Snow,
});

function initializeWYSIWYG() {
    $('[data-wysiwyg]:not([id])').each(function() {
        this.id = randomString(64);

        new Quill(this, {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline', 'image'],
                    [{'list': 'ordered'}, {'list': 'bullet'}]
                ]
            },
            formats: [
                'header',
                'bold', 'italic', 'underline', 'strike', 'blockquote',
                'list', 'bullet', 'indent', 'link', 'image'
            ],
            theme: 'snow',
        });
    });
}

export function randomString(length) {
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const charactersLength = characters.length;
    for(let i = 0; i < length; i++) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }

    return result;
}

//password toggle eye icon
$(document).on(`click`, `.show-password span`, function() {
    const $input = $(this).parents(`label`).find(`input`);

    if($input.attr(`type`) === `password`) {
        $input.attr(`type`, `text`);
    } else {
        $input.attr(`type`, `password`);
    }
})

$(document).ready(() => $(`[data-toggle="dropdown"]`).each(function() {
    initializeDropdown($(this))
}));

$(document).arrive(`[data-toggle="dropdown"]`, function() {
    initializeDropdown($(this));
});

function initializeDropdown($button) {
    const $dropdown = $button.siblings(`.dropdown-menu`);

    $button.click(function() {
        if($dropdown.is(`:visible`)) {
            $dropdown.hide();
        } else {
            $(`.dropdown-menu`).hide();
            $dropdown.show();
        }

        createPopper($button[0], $dropdown[0], {
            placement: `left`,
        });
    })
}

$(document).ready(() => {
    const $currentUser = $(`#current-user`);

    if($currentUser.exists()) {
        $(`.current-user`).click(() => {
            const ajax = AJAX.route(`POST`, `user_edit_template`, {
                user: $currentUser.val()
            });

            Modal.load(ajax)
        });
    }
})
