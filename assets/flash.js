import $ from "jquery";

export const INFO = `info`;
export const SUCCESS = `success`;
export const WARNING = `warning`;
export const ERROR = `danger`;

export default class Flash {

    static serverError(error = null) {
        if(error) {
            console.error(`%cServer error : %c${error}`, ...[
                `font-weight: bold;`,
                `font-weight: normal;`,
            ]);
        }

        Flash.add(ERROR, `Une erreur est survenue lors du traitement de votre requête par le serveur`);
    }

    static add(type, message, unique = false) {
        const $alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        const $container = $('.alert-container');
        if(unique) {
            Flash.clear();
        }

        $alert.appendTo(`.alert-container`);
        $container.css('z-index', 10000);

        setTimeout(() => $alert.fadeOut(500, function() {
            $(this).remove();
        }), 5000);
    }

    static clear() {
        $('.alert-container').empty();
    }
}
