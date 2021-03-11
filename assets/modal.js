import Flash from './flash';
import AJAX from './ajax';
import {LOADING_CLASS} from "./app";

const uploads = {};

function addUpload(modal, name, file) {
    if(uploads[modal.id] === undefined) {
        uploads[modal.id] = {};
    }

    uploads[modal.id][name] = file;
}

export default class Modal {
    id;
    element;
    config;
    files;

    static static(element, config = {}) {
        const modal = new Modal();
        modal.id = Math.floor(Math.random() * 1000000);
        modal.element = typeof element === `string` ? $(element) : element;
        modal.config = config instanceof AJAX ? {ajax: config} : config;

        if(!modal.element || !modal.element.exists()) {
            console.error(`Could not find HTML element of modal ${element}`);
            return null;
        }

        modal.setupFileUploader();

        modal.element.on('hidden.bs.modal', () => modal.clear());
        modal.element.on('shown.bs.modal', () => {
            if(config.afterOpen) {
                config.afterOpen(modal);
            }
        });
        modal.element.find(`button[type="submit"]`).click(function() {
            const $button = $(this);
            if($button.hasClass(LOADING_CLASS)) {
                Flash.add(Flash.WARNING, `Opération en cours d'exécution`);
            }

            $button.load(() => {
                return config.submitter ? config.submitter() : modal.handleSubmit()
            });
        });

        return modal;
    }

    static load(ajax, config) {
        if(typeof ajax === 'string') {
            withResponse({
                template: ajax,
            });
        } else {
            ajax.json(response => withResponse(response))
        }

        function withResponse(response) {
            const $modal = $(response.template);
            $modal.appendTo(`body`);
            $modal.modal(`show`);

            $modal.on('hidden.bs.modal', function(e) {
                $(this).remove();
            })

            const modal = new Modal();
            modal.id = Math.floor(Math.random() * 1000000);
            modal.element = $modal;
            modal.config = {
                ajax: AJAX.url(`POST`, config.submit ?? response.submit),
                ...config
            };

            modal.setupFileUploader();

            if(config.afterOpen) {
                config.afterOpen(modal);
            }

            $modal.find(`button[type="submit"]`).click(function() {
                const $button = $(this);
                if($button.hasClass(LOADING_CLASS)) {
                    Flash.add(Flash.WARNING, `Opération en cours d'exécution`);
                }

                $button.load(() => modal.handleSubmit());
            });
        }
    }

    setupFileUploader() {
        const modal = this;
        const $dropframe = this.element.find(`.attachment-drop-frame`);
        const $input = $dropframe.find(`input[type="file"]`);

        if($dropframe.exists()) {
            [`dragenter`, `dragover`, `dragleave`, `drop`].forEach(event => {
                $dropframe.on(event, function(event) {
                    event.preventDefault();
                    return false;
                });
            })

            $input.on(`change`, function() {
                addUpload(modal, $input.attr(`name`), $(this)[0].files[0]);
                $dropframe.addClass(`is-valid`);
            });

            $dropframe.on(`drop`, function(event) {
                const data = event.originalEvent.dataTransfer;
                const $dropframe = $(this);

                $dropframe.siblings(`.invalid-feedback`).remove();
                $dropframe.removeClass(`is-valid is-invalid`);

                if(data && data.files.length) {
                    event.preventDefault();
                    event.stopPropagation();

                    const supported = $input.data(`format`);
                    const format = getExtension(data.files[0]);
                    if(supported && format !== supported) {
                        $dropframe.addClass(`is-invalid`);
                        $dropframe.parents(`label`).append(`<span class="invalid-feedback">Seuls les fichier au format .${supported} sont supportés</span>`);
                    } else {
                        addUpload(modal, $input.attr(`name`), data.files[0]);
                        $dropframe.addClass(`is-valid`);
                    }
                } else {
                    $dropframe.addClass(`is-invalid`);
                }

                return false;
            });
        }
    }

    handleSubmit() {
        const data = processForm(this);
        if(data === false) {
            return;
        }
        if(this.config.ajax) {
            return this.config.ajax.json(data, result => {
                if (!result.success && result.errors !== undefined) {
                    for (const error of result.errors.fields) {
                        showInvalid(this.element.find(`[name="${error.field}"]`), error.message);
                    }

                    return;
                }

                //refresh the datatable
                if (this.config && this.config.table) {
                    if (this.config.table.ajax) {
                        this.config.table.ajax.reload();
                    } else {
                        $(this.config.table).DataTable().ajax.reload();
                    }
                }

                if (result.menu) {
                    $(`#menu-dropdown`).replaceWith(result.menu);
                }

                this.element.modal(`hide`);

                if (result.success && this.config.success) {
                    this.config.success(result);
                }
            });
        } else {
           return new Promise((resolve) => {
               this.element.modal(`hide`);
               resolve();
           });
        }
    }

    open(data = {}) {
        for(let [name, value] of Object.entries(data)) {
            this.element.find(`[data-display="${name}"]`).html(value);
            this.element.find(`input.data[name="${name}"]`).val(value);
        }

        this.element.modal(`show`);
    }

    close() {
        this.element.modal(`hide`);
    }

    clear() {
        clearForm(this.element);
    }

    elem() {
        return this.element;
    }
}

export function clearForm($elem) {
    $elem.find(`input.data:not([type=checkbox]):not([type=radio]), select.data, input[data-repeat], textarea.data`).val(null).trigger(`change`);
    $elem.find(`input[type=checkbox][checked], input[type=radio][checked]`).prop(`checked`, false);

    for(const check of $elem.find(`input[type=checkbox][checked], input[type=radio][checked]`)) {
        $(check).prop(`checked`, true);
    }

    $elem.find(`.is-invalid, .is-valid`).removeClass(`is-invalid is-valid`);
    $elem.find(`.invalid-feedback`).remove();
    $elem.find(`[contenteditable="true"]`).html(``);
}

export function processForm($parent) {
    let modal = null;
    if($parent instanceof Modal) {
        modal = $parent;
        $parent = modal.elem();
    }

    const errors = [];
    const data = new FormData();
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
            if(!(modal && $input.is(`[type="file"]`)) || !uploads[modal.id][$input.attr(`name`)]) {
                errors.push({
                    elements: [$input],
                    message: `Ce champ est requis`,
                });
            }
        }

        if($input.attr(`name`)) {
            let value = $input.val() || null;
            if($input.attr(`type`) === `checkbox`) {
                value = $input.is(`:checked`) ? `1` : `0`;
            } else if(typeof value === 'string') {
                value = $input.val().trim();
            }

            if(value !== null) {
                data.append($input.attr(`name`), value);
            }
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
        data.append(name, elements
            .map(elem => $(elem))
            .map($elem => {
                if($elem.attr(`type`) === `checkbox`) {
                    return $elem.is(`:checked`) ? $elem.val() : null;
                } else {
                    return $elem.val()
                }
            })
            .filter(val => val !== null));
    }

    if(modal && uploads[modal.id]) {
        for(const [name, file] of Object.entries(uploads[modal.id])) {
            data.append(name, file)
        }
    }

    for(const error of errors) {
        error.elements.forEach($elem => showInvalid($elem, error.message));
    }

    return errors.length === 0 ? data : false;
}

function showInvalid($field, message) {
    if($field.is(`[data-s2]`)) {
        $field = $field.parent().find(`.select2-selection`);
    } else if($field.is(`[type="file"]`)) {
        $field = $field.parent();
    }

    $field.addClass(`is-invalid`);
    $field.parents(`label`).append(`<span class="invalid-feedback">${message}</span>`);
}

function getExtension(file) {
    return file.name.split('.').pop();
}
