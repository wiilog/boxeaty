import $ from 'jquery';

export default class Select2 {
    static init($element) {
        console.log($element);
        console.log($element.data(`placeholder`));
        $element.prepend(`<option selected>`);
        $element.select2({
            placeholder: $element.data(`placeholder`),
            language: {
                noResults: function () {
                    return 'Aucun résultat';
                }
            },
        });
        console.log("ok?");
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))))
    .arrive(`[data-s2]`, function() {
        Select2.init($(this));
    });
