import $ from "jquery";

export const DATATABLE_ACTIONS_TITLE = `<span style="display:block;text-align:center">Actions</span>`;
export const DATATABLE_ACTIONS = {
    data: `actions`,
    title: DATATABLE_ACTIONS_TITLE,
    orderable: false,
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
            columnDefs: [
                {width: `30px`, targets: config.columns.length - 1}
            ],
            language: {
                url: `/i18n/datatableLanguage.json`,
            },
            dom: `<"row mb-2"<"col-auto d-none"f>>t
            <"footer"
                <"left" li>
                p
            >r`,
            initComplete: () => {
                moveSearchInputToHeader($table);
            },
            ...config
        });


    $(`${table} tbody`)
        .on(`dblclick`, `tr`, function() {
            config.listeners.action($datatable.row(this).data());
        })
        .on(`click`, `.datatable-action [data-listener]`, function() {
            const $button = $(this);
            const row = $datatable.row($button.parents(`tr`));
            const callback = config.listeners[$(this).data(`listener`)];

            callback(row.data())
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
