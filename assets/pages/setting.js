import '../app';

import $ from "jquery";
import {processForm} from "../modal";
import AJAX from "../ajax";

$(`button[type="submit"]`).click(() => AJAX.route(`POST`, `settings_update`).json(processForm($(`.global-settings`))));
