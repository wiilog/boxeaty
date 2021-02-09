export const DATATABLE_ACTIONS_TITLE = `<span style="display:block;text-align:right">Actions</span>`;

export function initDatatable(id, config) {
    const $table = $(`#${id}`);
    $table.addClass(`w-100`);

    for(let column of config.columns) {
        if(!column.name) {
            column.name = column.data;
        }
    }

    return $table
        .on(`error.dt`, (e, settings, techNote, message) => console.error(`An error has been reported by DataTables: `, message, e, config.id))
        .dataTable({
            processing: true,
            serverSide: true,
            fixedColumns:   {
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
}

function moveSearchInputToHeader($table) {
    const $newContainer = $(`#table-search-container`);
    const $container = $table.parents(`.dataTables_wrapper `).find(`.dataTables_filter`);
    const $input = $container.find(`input`);

    $input.attr(`placeholder`, `Rechercher`)
    $input.appendTo($newContainer);
}
