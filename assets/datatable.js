import $ from "jquery";
import {clearForm, processForm} from "./modal";

export const DATATABLE_ACTIONS_TITLE = `<span style="display:block;text-align:center">Actions</span>`;
export const DATATABLE_ACTIONS = {
    data: `actions`,
    title: DATATABLE_ACTIONS_TITLE,
    orderable: false,
    width: `10px`,
};

export const DATATABLE_LANGUAGE = {
    processing: `<span class="content">Traitement en cours</span>`,
    search: `Rechercher&nbsp;:`,
    lengthMenu: `Nombre de lignes par page: _MENU_`,
    info: `_START_ &agrave; _END_ sur _TOTAL_`,
    infoEmpty: `Aucun &eacute;l&eacute;ment &agrave; afficher`,
    infoFiltered: `(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)`,
    infoPostFix: ``,
    loadingRecords: `Chargement en cours`,
    zeroRecords: `Aucun &eacute;l&eacute;ment &agrave; afficher`,
    emptyTable: `Aucune donn&eacute;e disponible dans le tableau`,
    paginate: {
        first: `Premier`,
        previous: `Pr&eacute;c&eacute;dent`,
        next: `Suivant`,
        last: `Dernier`
    },
    aria: {
        sortAscending: `: activer pour trier la colonne par ordre croissant`,
        sortDescending: `: activer pour trier la colonne par ordre d&eacute;croissant`
    }
};

export function initDatatable(table, config) {
    const $table = $(table);
    $table.addClass(`w-100`);

    const orderConfig = $table.data(`default-order`);
    config.order = Array.isArray(orderConfig) ? orderConfig : [];

    for(const [id, column] of Object.entries(config.columns)) {
        if(!column.name) {
            column.name = column.data;
        }

        if(config.order && Array.isArray(config.order)) {
            const newOrder = [];
            for(let [name, order] of config.order) {
                if(name === column.data) {
                    name = Number(id);
                }

                newOrder.push([name, order]);
            }

            config.order = newOrder;
        }
    }

    const ajax = config.ajax;
    config.ajax = (content, callback) => {
        content.filters = {};

        const $filters = $(`.filters`);
        if ($filters.exists()) {
            const data = processForm($filters);
            if (data) {
                data.forEach((value, key) => {
                    content.filters[key] = value;
                });
            }
        }

        ajax.json(content)
            .then(data => {
                callback(data);
                if (config.onFilter) {
                    config.onFilter(data);
                }
            });
    };

    const initial = $table.data(`initial-data`);
    if(initial && typeof initial === `object`) {
        config = {
            ...config,
            data: initial.data,
            deferLoading: [initial.recordsFiltered || 0, initial.recordsTotal || 0],
        };
    }

    const $datatable = $table
        .on(`error.dt`, (e, settings, techNote, message) => console.error(`An error has been reported by DataTables: `, message, e, table))
        .DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            scrollX: true,
            autoWidth: true,
            fixedColumns: {
                heightMatch: `auto`
            },
            language: DATATABLE_LANGUAGE,
            dom: `<"row mb-2"<"col-auto d-none"f>>t<"footer"<"left" li>p>r`,
            initComplete: () => {
                moveSearchInputToHeader($table);
            },
            ...config
        });

    $(`${table} tbody`)
        .on(`click`, `tr`, function(event) {
            if(!$(event.target).parents(`.datatable-action`).exists() && config.listeners.action) {
                $(this).load(() => config.listeners.action($datatable.row(this).data()));
            }
        })
        .on(`click`, `[data-listener]`, function() {
            const $button = $(this);
            const row = $datatable.row($button.parents(`tr`));
            const callback = config.listeners[$(this).data(`listener`)];

            if(callback) {
                callback(row.data(), $button)
            }
        });

    $(`.filters .filter`).click(() => $datatable.ajax.reload());
    $(`.filters .empty-filters`).click(function() {
        clearForm($(this).parents(`.filters`))
    });

    return $datatable;
}

function moveSearchInputToHeader($table) {
    const $newContainer = $(`#table-search-container`);
    const $container = $table.parents(`.dataTables_wrapper `).find(`.dataTables_filter`);
    const $input = $container.find(`input`);

    $input.attr(`placeholder`, `Rechercher`)
    $input.appendTo($newContainer);
}
