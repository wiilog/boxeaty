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

                $button.load(() => modal.handleSubmit(() => $modal.remove()));
            });
        })
    }

    handleSubmit(then) {
        const data = processForm(this.element);
        if(data === false) {
            return;
        }

        this.config.ajax.json(data, result => {
            //refresh the datatable
            if(this.config && this.config.table) {
                if(this.config.table.ajax) {
                    this.config.table.ajax.reload();
                } else {
                    $(this.config.table).DataTable().ajax.reload();
                }
            }

            this.element.modal(`hide`);
            if(then) {
                then();
            }
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

function processForm($parent) {
    const errors = [];
    const data = {};
    const $inputs = $parent.find(`select.data, input.data, input[data-repeat]`);

    //clear previous errors
    $parent.find(`.is-invalid`).removeClass(`is-invalid`);
    $parent.find(`.invalid-feedback`).remove();

    for(const input of $inputs) {
        let $input = $(input);

        if($input.attr(`type`) === `radio`) {
            $input = $parent.find(`input[type="radio"][name="${$input.name}"]:checked`);
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

        if(input.name) {
            data[input.name] = input.value;
        }
    }

    for(const error of errors) {
        error.elements.forEach($elem => {
            $elem.addClass(`is-invalid`);
            $elem.parents(`label`).append(`<span class="invalid-feedback">${error.message}</span>`);
        });
    }

    return errors.length === 0 ? data : false;
}
