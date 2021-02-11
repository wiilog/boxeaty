import Flash from './flash';
import AJAX from './ajax';

export default class Modal {
    element;
    config;

    static init(element, config) {
        const modal = new Modal();
        modal.element = typeof element === `string` ? $(element) : element;
        modal.config = config instanceof AJAX ? {ajax: config} : config;

        if(!modal.element || !modal.element.exists()) {
            console.error(`Could not find HTML element of modal ${element}`);
            return null;
        }

        modal.element.find(`button[type="submit"]`).click(() => {
            const data = processForm(modal.element);
            modal.config.ajax.json(data).then(result => {
                if(result.status === 500) {
                    console.error(result);
                    Flash.add("Une erreur est survenue lors du traitement de votre requête par le serveur");
                }

                if(result.success) {
                    Flash.add(Flash.SUCCESS, result.msg);
                } else {
                    Flash.add(Flash.DANGER, result.msg);
                }

                modal.element.modal(`hide`);
            })
        });

        return modal;
    }

    open(data) {
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
    const data = {};
    const $inputs = $parent.find(`input.data`);

    for(const input of $inputs) {
        data[input.name] = input.value;
    }

    return data;
}
