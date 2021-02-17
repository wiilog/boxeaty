export default class Select2 {
    static init($element) {
        $element.select2({
            language: {
                noResults: function () {
                    return 'Aucun résultat';
                }
            },
        })
    }
}
