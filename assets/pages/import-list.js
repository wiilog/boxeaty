import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {initDatatable} from "../datatable";

$(document).ready(() => {
    const newImportModal = Modal.static(`#modal-new-import`, {
        ajax: AJAX.route(`POST`, `import_new`),
        table: `#table-imports`,
        success: result => {
            if(result.modal) {
                Modal.load(result.modal, {
                    table: `#table-imports`,
                    submit: Routing.generate(`import_fields_association`),
                    afterOpen: onSecondStepSuccess,
                });
            }
        }
    });

    $(`.new-import`).click(() => newImportModal.open());

    const table = initDatatable(`#table-imports`, {
        ajax: AJAX.route(`POST`, `imports_api`),
        columns: [
            {data: `name`, title: `Nom de l'import`},
            {data: `status`, title: `Statut`},
            {data: `creationDate`, title: `Date de création`},
            {data: `executionDate`, title: `Date d'exécution`},
            {data: `creations`, title: `Créations`},
            {data: `updates`, title: `Mises à jour`},
            {data: `errors`, title: `Erreurs`},
            {data: `user`, title: `Utilisateur`},
            {data: `actions`, title: `Actions`},
        ],
        order: [[`creationDate`, `desc`]],
        listeners: {
            cancel: data => AJAX.route(`POST`, `import_cancel`).run({
                import: data.id,
            }),
        }
    });
});

function onSecondStepSuccess(modal) {
    const $modal = modal.elem();

    $modal.find(`table`).DataTable({
        responsive: true,
        scrollX: false,
        autoWidth: true,
        paging: false,
        ordering: false,
        info: false,
        searching: false,
        fixedColumns: {
            heightMatch: `auto`
        },
        language: {
            url: `/i18n/datatableLanguage.json`,
        },
    });

    let modifying = false;
    $modal.find(`select`).on(`change`, function() {
        if(modifying) {
            return;
        }

        modifying = true;
        const $selects = $modal.find(`select`);

        const selection = $selects
            .map((i, select) => select.value)
            .filter(select => select !== "");

        $selects.each(function() {
            const $select = $(this);
            $select.find(`option`).attr(`disabled`, false);

            for(const item of selection) {
                if($select.val() !== item) {
                    $select.find(`option[value="${item}"]`).prop(`disabled`, true);
                }
            }

            $select.trigger(`change`);
        });

        modifying = false;
    });
}
