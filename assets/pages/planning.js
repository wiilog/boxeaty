import '../app';

import $ from "jquery";
import {processForm} from "../modal";

import "../styles/pages/planning.scss";
import AJAX from "../ajax";

$(document).ready(() => {
    const $filters = $(`.filters`);

    $filters.find(`.filter`).click(function() {
        const params = processForm($filters).asObject();

        AJAX.route(`GET`, `planning_content`, params).json(data => {
            $(`#planning`).html(data.planning);
        });
    });
});
