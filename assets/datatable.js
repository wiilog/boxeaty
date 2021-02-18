import $ from "jquery";
import {processForm} from "./modal";

export const DATATABLE_ACTIONS_TITLE = `<span style="display:block;text-align:center">Actions</span>`;
export const DATATABLE_ACTIONS = {
    data: `actions`,
    title: DATATABLE_ACTIONS_TITLE,
    orderable: false,
    width: `10px`,
};

export function initDatatable(table, config) {
    const $table = $(table);
    $table.addClass(`w-100`);

    for(const [id, column] of Object.entries(config.columns)) {
        if(!column.name) {
            column.name = column.data;
        }

        if(config.order && Array.isArray(config.order)) {
            const newOrder = [];
            for(let [name, order] of config.order) {
                if(name === column.data) {
                    name = id;
                }

                newOrder.push([name, order]);
            }

            config.order = newOrder;
        }
    }

    const ajax = config.ajax;
    config.ajax = (content, callback) => {
        const $filters = $(`.filters`);
        if($filters.exists()) {
            content.filters = processForm($filters);
        }

        ajax.json(content, data => {
            callback(data);
        });
    };

    const $datatable = $table
        .on(`error.dt`, (e, settings, techNote, message) => console.error(`An error has been reported by DataTables: `, message, e, table))
        .DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            scrollX: false,
            autoWidth: true,
            fixedColumns: {
                heightMatch: `auto`
            },
            language: {
                url: `/i18n/datatableLanguage.json`,
            },
            dom: `<"row mb-2"<"col-auto d-none"f>>t<"footer"<"left" li>p>r`,
            initComplete: () => {
                moveSearchInputToHeader($table);
            },
            ...config
        });


    $(`${table} tbody`)
        .on(`dblclick`, `tr`, function() {
            if(config.listeners.action) {
                config.listeners.action($datatable.row(this).data());
            }
        })
        .on(`click`, `.datatable-action [data-listener]`, function() {
            const $button = $(this);
            const row = $datatable.row($button.parents(`tr`));
            const callback = config.listeners[$(this).data(`listener`)];

            if(callback) {
                callback(row.data())
            }
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
