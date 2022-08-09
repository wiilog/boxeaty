import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newBoxModal = Modal.static(`#modal-new-box`, {
        ajax: AJAX.route(`POST`, `box_new`),
        table: `#table-boxes`,
    });

    $(`.new-box`).click(() => newBoxModal.open());

    const table = initDatatable(`#table-boxes`, {
        ajax: AJAX.route(`POST`, `boxes_api`),
        columns: [
            {data: `id`, class: `d-none`},
            {data: `number`, title: `Code`},
            {data: `creationDate`, title: `Date de création`},
            {data: `isBox`, title: `Box ou caisse`},
            {data: `location`, title: `Emplacement`},
            {data: `state`, title: `Etat`},
            {data: `quality`, title: `Qualité`},
            {data: `owner`, title: `Propriétaire`},
            {data: `type`, title: `Type`},
        ],
        listeners: {
            action: data => {
                window.location.href = Routing.generate(`box_show`, {
                    box: data.id
                });
            },
        }
    });
});
