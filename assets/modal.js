import Flash from './flash';
import AJAX from './ajax';
import {LOADING_CLASS} from "./app";

export default class Modal {
    element;
    config;

    static static(element, config) {
        const modal = new Modal();
        modal.element = typeof element === `string` ? $(element) : element;
        modal.config = config instanceof AJAX ? {ajax: config} : config;

        if(!modal.element || !modal.element.exists()) {
            console.error(`Could not find HTML element of modal ${element}`);
            return null;
        }

        modal.element.find(`button[type="submit"]`).click(function() {
            const $button = $(this);
            if($button.hasClass(LOADING_CLASS)) {
                Flash.add(Flash.WARNING, `Opération en cours d'exécution`);
            }

            $button.load(() => modal.handleSubmit());
        });

        return modal;
    }

    static load(ajax, config) {
        ajax.json(response => {
            const $modal = $(response.template);
            $modal.appendTo(`body`);
            $modal.modal(`show`);

            $modal.on('hidden.bs.modal', function (e) {
                $(this).remove();
            })

            $modal.find(`button[type="submit"]`).click(function() {
                const $button = $(this);
                if($button.hasClass(LOADING_CLASS)) {
                    Flash.add(Flash.WARNING, `Opération en cours d'exécution`);
                }

                const modal = new Modal();
                modal.element = $modal;
                modal.config = {
                    ajax: AJAX.url(`POST`, response.submit),
                    ...config
                };

                $button.load(() => modal.handleSubmit());
            });
        })
    }

    handleSubmit() {
        const data = processForm(this.element);
        if(data === false) {
            return;
        }

        return this.config.ajax.json(data, result => {
            if(!result.success && result.errors !== undefined) {
                for(const error of result.errors.fields) {
                    showInvalid(this.element.find(`[name="${error.field}"]`), error.message);
                }

                return;
            }

            //refresh the datatable
            if(this.config && this.config.table) {
                if(this.config.table.ajax) {
                    this.config.table.ajax.reload();
                } else {
                    $(this.config.table).DataTable().ajax.reload();
                }
            }

            this.element.modal(`hide`);
        });
    }

    open(data = {}) {
        for(let [name, value] of Object.entries(data)) {
            this.element.find(`[data-display="${name}"]`).html(value);
            this.element.find(`input.data[name="${name}"]`).val(value);
        }

        this.element.modal(`show`);
    }

    clear() {
        console.error("Modal clearing not implemented");
    }

    elem() {
        return this.element;
    }
}

export function processForm($parent) {
    const errors = [];
    const data = {};
    const $inputs = $parent.find(`select.data, input.data, input[data-repeat], textarea.data`);

    //clear previous errors
    $parent.find(`.is-invalid`).removeClass(`is-invalid`);
    $parent.find(`.invalid-feedback`).remove();

    for(const input of $inputs) {
        let $input = $(input);

        if($input.attr(`type`) === `radio`) {
            $input = $parent.find(`input[type="radio"][name="${input.name}"]:checked`);
        }

        if($input.data(`repeat`)) {
            const $toRepeat = $parent.find(`input[name="${$input.data(`repeat`)}"`);

            if($input.val() !== $toRepeat.val()) {
                errors.push({
                    elements: [$input, $toRepeat],
                    message: `Les champs ne sont pas identiques`,
                });
            }
        }

        if($input.is(`[required]`) && !$input.val()) {
            errors.push({
                elements: [$input],
                message: `Ce champ est requis`,
            });
        }

        if(input.name && $input.val() !== "") {
            data[input.name] = $input.val();
        }
    }

    const $arrays = $parent.find(`select.data-array, input.data-array`);
    const grouped = {};
    for(const element of $arrays) {
        if(grouped[element.name] === undefined) {
            grouped[element.name] = [];
        }

        grouped[element.name].push(element);
    }

    for(const [name, elements] of Object.entries(grouped)) {
        data[name] = elements
            .map(elem => $(elem))
            .map($elem => {
                if($elem.attr(`type`) === `checkbox`) {
                    return $elem.is(`:checked`) ? $elem.val() : null;
                } else {
                    return $elem.val()
                }
            })
            .filter(val => val !== null);
    }

    for(const error of errors) {
        error.elements.forEach($elem => showInvalid($elem, error.message));
    }

    return errors.length === 0 ? data : false;
}

function showInvalid($field, message) {
    $field.addClass(`is-invalid`);

    if($field.is(`[data-s2]`)) {
        $field = $field.parent().find(`.select2-selection`);
    }

    $field.parents(`label`).append(`<span class="invalid-feedback">${message}</span>`);
}
