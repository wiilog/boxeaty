import AJAX from './ajax';
import $ from "jquery";

const uploads = {};

function addUpload(modal, name, file) {
    if(uploads[modal.id] === undefined) {
        uploads[modal.id] = {};
    }

    uploads[modal.id][name] = file;
}

function deleteUpload(modal, name) {
    if(uploads[modal.id] && uploads[modal.id][name]) {
        delete uploads[modal.id][name];
    }
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

        modal.element.on('hidden.bs.modal', () => {
            modal.clear();

            modal.element.find('[data-s2-initialized]').each(function() {
                //close all select2 elements
                $(this).select2('close');
            });

            if(config.afterHidden) {
                config.afterHidden(modal);
            }
        });

        modal.element.on('shown.bs.modal', () => {
            if(config.afterOpen) {
                config.afterOpen(modal);
            }
        });

        modal.element.find(`button[type="submit"]`).click(function() {
            const $button = $(this);

            $button.load(function() {
                return config.submitter ? config.submitter($button) : modal.handleSubmit($button)
            });
        });

        return modal;
    }

    static load(ajax, config = {}) {
        if(typeof ajax === 'string') {
            Modal.html({
                ...config,
                template: ajax,
            });
        } else {
            ajax
                .json()
                .then((response) => {
                    delete response.success;

                    Modal.html({
                        ...config,
                        ...response,
                    });
                })
                .catch(() => {
                    if (config.error) {
                        config.error();
                    }
                });
        }
    }

    static html(config = {}) {
        const $modal = $(config.template);
        $modal.appendTo(`body`);
        $modal.modal(`show`);

        if(config.afterShown) {
            $modal
                .on('shown.bs.modal', function() {
                        config.afterShown(modal);
                });
        }

        $modal
            .on('hidden.bs.modal', function() {
                $(this).remove();

                modal.element.find('[data-s2-initialized]').each(function() {
                    //close all select2 elements
                    $(this).select2('close');
                });

                if(config.afterHidden) {
                    config.afterHidden(modal);
                }
            });

        const modal = new Modal();
        modal.id = Math.floor(Math.random() * 1000000);
        modal.element = $modal;
        modal.config = {
            ...config,
            ajax: AJAX.url(`POST`, config.submit),
        };

        modal.setupFileUploader();

        if(config.afterOpen) {
            config.afterOpen(modal);
        }

        $modal.find(`button[type="submit"]`).on('click', function() {
            const $button = $(this);
            $button.load(() => modal.handleSubmit($button));
        });


        if(config.onPrevious) {
            $modal.find(`button[name="previous"]`).on('click', function () {
                config.onPrevious(modal);
            });
        }

        return modal;
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
            });

            this.element.find('.file-empty').on('click', (e) => {
                $input.trigger('click');
                e.preventDefault();
            });

            const $fileEmpty = $dropframe.find('.file-empty');
            const $fileConfirmation = $dropframe.find('.file-confirmation');
            $input.on('change', function() {
                const files = $(this)[0].files;
                if(files && files.length > 0) {
                    proceedFileSaving($input, files[0], $fileEmpty, $fileConfirmation, modal);
                } else {
                    deleteUpload(modal, $input.attr(`name`));
                    $dropframe.removeClass('is-valid');
                    $fileEmpty.removeClass('d-none');
                    $fileConfirmation.addClass('d-none');
                }
            });

            $fileConfirmation.find('.file-delete-icon').on('click', function(e) {
                $input.val('').trigger('change');
                const $button = $(this);
                const $fileInformation = $button.closest('.file-confirmation');
                const $imageVisualisation = $fileInformation.find('.image-visualisation');
                if ($imageVisualisation.exists()) {
                    $imageVisualisation.attr('src', '');
                }
                const $fileDeleted = $fileInformation.find('[name="fileDeleted"]');
                if ($fileDeleted.exists()) {
                    $fileDeleted.val(1);
                }
                e.preventDefault();
            });

            $dropframe.on(`drop`, function(event) {
                const data = event.originalEvent.dataTransfer;
                const $dropframe = $(this);

                $dropframe.siblings(`.invalid-feedback`).remove();
                $dropframe.removeClass(`is-valid is-invalid`);

                if(data && data.files.length) {
                    event.preventDefault();
                    event.stopPropagation();
                    proceedFileSaving($input, data.files[0], $fileEmpty, $fileConfirmation, modal)
                } else {
                    $dropframe.addClass(`is-invalid`);
                }

                return false;
            });
        }
    }

    handleSubmit($button) {
        const data = processForm(this, $button);
        if(data === false) {
            return;
        }

        if(this.config.ajax) {
            return this.config.ajax
                .json(data)
                .then(result => {
                    if(!handleErrors(this.element, result)) {
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

                    if(result.menu) {
                        $(`#menu-dropdown`).replaceWith(result.menu);
                    }

                    if(result.modal) {
                        delete result.success;
                        delete result.menu;

                        Modal.html({
                            ...this.config,
                            ...result.modal,
                        });
                    }

                    if(!this.config.keepOpen) {
                        this.close();
                    }

                    if(result.success && this.config.success) {
                        this.config.success(result);
                    }
                });
        } else {
            return new Promise((resolve) => {
                if(!this.config.keepOpen) {
                    this.close();
                }

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
    $elem.find(`input.data:not([type=checkbox]):not([type=radio]):not([type=hidden]), select.data, input[data-repeat], textarea.data`).val(null).trigger(`change`);
    $elem.find(`input[type=checkbox]:checked, input[type=radio]:checked`).prop(`checked`, false);

    for(const check of $elem.find(`input[type=checkbox][checked], input[type=radio][checked]`)) {
        $(check).prop(`checked`, true);
    }

    $elem.find(`.is-invalid, .is-valid`).removeClass(`is-invalid is-valid`);
    $elem.find(`.invalid-feedback`).remove();
    $elem.find(`[contenteditable="true"]`).html(``);
}

export function processForm($parent, $button = null, classes = {data: `data`, array: `data-array`}) {
    let modal = null;
    if($parent instanceof Modal) {
        modal = $parent;
        $parent = modal.elem();
    }

    const errors = [];
    const data = new FormData();
    const $inputs = $parent.find(`select.${classes.data}, input.${classes.data}, input[data-repeat], textarea.${classes.data}, .data[data-wysiwyg]`);

    //clear previous errors
    $parent.find(`.is-invalid`).removeClass(`is-invalid`);
    $parent.find(`.invalid-feedback`).remove();

    for(const input of $inputs) {
        let $input = $(input);

        if($input.attr(`type`) === `radio`) {
            const $checked = $parent.find(`input[type="radio"][name="${input.name}"]:checked`);
            if($checked.exists()) {
                $input = $checked;
            } else {
                $input = $parent.find(`input[type="radio"][name="${input.name}"]`);
            }
        } else if($input.attr(`type`) === `number`) {
            let val = parseInt($input.val());
            let min = parseInt($input.attr('min'));
            let max = parseInt($input.attr('max'));

            if(!isNaN(val) && (val > max || val < min)) {
                let message = `La valeur `;
                if(!isNaN(min) && !isNaN(max)) {
                    message += min > max
                        ? `doit être inférieure à ${max}.`
                        : `doit être comprise entre ${min} et ${max}.`;
                } else if(!isNaN(max)) {
                    message += `doit être inférieure à ${max}.`;
                } else if(!isNaN(min)) {
                    message += `doit être supérieure à ${min}.`;
                } else {
                    message += `est invalide`;
                }

                errors.push({
                    elements: [$input],
                    message,
                });
            }
        } else if($input.attr(`type`) === `tel`) {
            const regex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})$/;
            if($input.val() && !$input.val().match(regex)) {
                errors.push({
                    elements: [$input],
                    message: `Le numéro de téléphone n'est pas valide`,
                });
            }
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

        if($input.is(`[required]`) || $input.is(`[data-required]`)) {
            if(([`radio`, `checkbox`].includes($input.attr(`type`)) && !$input.is(`:checked`))) {
                errors.push({
                    global: true,
                    message: `Vous devez sélectionner au moins un élément`,
                });
            } else if($input.is(`[data-wysiwyg]`) && !$input.find(`.ql-editor`).text() || !$input.is(`[data-wysiwyg]`) && !$input.val()) {
                if (!(modal && $input.is(`[type="file"]`))
                    || !uploads[modal.id]
                    || !uploads[modal.id][$input.attr(`name`)]) {
                    errors.push({
                        elements: [$input],
                        message: `Ce champ est requis`,
                    });
                }
            }
        }

        if($input.attr(`name`) || $input.attr(`data-wysiwyg`)) {
            let value;
            if($input.is(`[data-wysiwyg]`)) {
                value = $input.find(`.ql-editor`).html();
            } else if($input.attr(`type`) === `checkbox`) {
                value = $input.is(`:checked`) ? `1` : `0`;
            } else {
                value = $input.val() || null;
            }

            if(typeof value === `string`) {
                value = value.trim();
            }
            if(value !== null) {
                data.append($input.attr(`name`) || $input.attr(`data-wysiwyg`), value);
            }
        }
    }

    addDataArray($parent, data, classes);

    if($button && $button.attr(`name`)) {
        data.append($button.attr(`name`), $button.val());
    }

    // add uploads
    if(modal && uploads[modal.id]) {
        for(const [name, file] of Object.entries(uploads[modal.id])) {
            data.append(name, file)
        }
    }

    if(modal && modal.config.processor) {
        modal.config.processor(data, errors, modal);
    }

    // display errors under each field
    $parent.find(`.global-error`).remove();
    for(const error of errors) {
        if(error.global) {
            showGlobalInvalid($parent, error.message);
        } else {
            error.elements.forEach($elem => showInvalid($elem, error.message));
        }
    }

    return errors.length === 0 ? data : false;
}

function addDataArray($parent, data, classes) {
    const $arrays = $parent.find(`select.${classes.array}, input.${classes.array}`);
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
}

export function handleErrors(element, result) {
    if(!result.success && result.errors !== undefined) {
        for(const error of result.errors.fields) {
            if(error.global) {
                showGlobalInvalid(element, error.message);
            } else {
                showInvalid(element.find(`[name="${error.field}"]`), error.message);
            }
        }

        return false;
    }

    return true;
}

function showInvalid($field, message) {
    let $parent;
    if($field.is(`[data-s2-initialized]`)) {
        $field = $field.parent().find(`.select2-selection`);
    } else if($field.is(`[type="file"]`)) {
        $field = $field.parent();
    }

    if($field.is(`[data-wysiwyg`)) {
        $parent = $field.parent();
    } else {
        $parent = $field.parents(`label`);
    }

    $field.addClass(`is-invalid`);
    $parent.append(`<span class="invalid-feedback">${message}</span>`);
}

function showGlobalInvalid($modal, message) {
    let $container = $modal.find(`.global-error`);
    if(!$container.exists()) {
        $container = $(`<div class="alert alert-danger mt-2 mb-0 global-error"></div>`);
        $container.appendTo($modal.find(`.body`));
    } else {
        $container.empty();
    }

    $container.html(message);
}

function getExtension(file) {
    return file.name.split('.').pop();
}

function proceedFileSaving($input, file, $fileEmpty, $fileConfirmation, modal) {
    const $dropframe = $input.closest(`.attachment-drop-frame`)
    const supported = $input.data(`format`);
    const format = getExtension(file);

    if(supported && format !== supported) {
        $input.val('').trigger('change');
        $dropframe.addClass(`is-invalid`);
        $dropframe.parents(`label`).append(`<span class="invalid-feedback">Seuls les fichier au format .${supported} sont supportés</span>`);
    } else {
        addUpload(modal, $input.attr(`name`), file);
        $dropframe.addClass('is-valid');
        $fileEmpty.addClass('d-none');
        $fileConfirmation.removeClass('d-none');
        $fileConfirmation.find('.file-name').text(file.name);

        const $imageVisualisation = $fileConfirmation.find('.image-visualisation');
        if ($imageVisualisation.exists()) {
            showImage(file, $imageVisualisation);
        }
    }
}

function showImage(file, $image) {
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            $image
                .attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    }
}
