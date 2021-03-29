import '../app';

import $ from "jquery";
import Modal from "../modal";
import AJAX from "../ajax";
import {DATATABLE_ACTIONS, DATATABLE_LANGUAGE, initDatatable} from "../datatable";

$(document).ready(() => {
    const newImportModal = Modal.static(`#modal-new-import`, {
        ajax: AJAX.route(`POST`, `import_new`),
        table: `#table-imports`,
        success: result => {
            if(result.next) {
                Modal.html({
                    template: result.next,
                    table: `#table-imports`,
                    submit: Routing.generate(`import_fields_association`),
                    afterOpen: onSecondStepSuccess,
                });
            }
        }
    });

    $(`.new-import`).click(() => newImportModal.open());

    initDatatable(`#table-imports`, {
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
            DATATABLE_ACTIONS,
        ],
        listeners: {
            cancel: data => AJAX.route(`POST`, `import_cancel`).json({
                import: data.id,
            }),
        }
    });

    $('#modal-new-import').find('[name=type]').on('change', function () {
        importTemplateChanged($(this));
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
        language: DATATABLE_LANGUAGE,
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

function importTemplateChanged($dataTypeImport = null) {
    const $linkToTemplate = $('.template-link');

    $linkToTemplate.empty();

    const templateDirectory = '/templates';
    const configDownloadLink = {
        box: {label: 'Box', url: `${templateDirectory}/Box.csv`},
    };

    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : '';
    if (configDownloadLink[valTypeImport]) {
        const {url, label} = configDownloadLink[valTypeImport];
        $linkToTemplate
            .append(`<div class="col-12">Un fichier de modèle d\'import est disponible pour les ${label}.</div>`)
            .append(`<div class="col-12"><a class="primary" href="${url}">Télécharger</a></div>`);
    }
    else if (valTypeImport === '') {
        $linkToTemplate.append('<div class="col-12">Des fichiers de modèles d\'import sont disponibles. Veuillez sélectionner un type de données à importer.</div>');
    }
    else {
        $linkToTemplate.append('<div class="col-12">Aucun modèle d\'import n\'est disponible pour ce type de données.</div>');
    }
}
