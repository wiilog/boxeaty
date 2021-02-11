import $ from "jquery";

export const DATATABLE_ACTIONS_TITLE = `<span style="display:block;text-align:right">Actions</span>`;
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
    console.log(config);
    const $datatable = $table
        .on(`error.dt`, (e, settings, techNote, message) => console.error(`An error has been reported by DataTables: `, message, e, table))
        .DataTable({
            processing: true,
            serverSide: true,
            fixedColumns: {
                heightMatch: `auto`
            },
            autoWidth: true,
            scrollX: true,
            language: {
                url: `/i18n/datatableLanguage.json`,
            },
            dom: `<"row mb-2"<"col-auto d-none"f>>t<"row align-items-center mt-4 mb-2"
                <"col-auto"l>
                <"col-auto"i>
                <"col"p>
            >r`,
            initComplete: () => {
                moveSearchInputToHeader($table);
            },
            ...config
        });


    $(`${table} tbody`)
        .on(`dblclick`, `tr`, function() {
            config.listeners.onAction($datatable.row(this).data());
        })
        .on(`click`, `.datatable-action [data-listener]`, function() {
            const $button = $(this);
            const callback = config.listeners[$(this).data(`listener`)];

            callback($datatable.row($button.parents(`tr`)).data())
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
