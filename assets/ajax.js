import Flash from "./flash";

export const GET = `POST`;
export const POST = `POST`;
export const PUT = `POST`;
export const PATCH = `POST`;
export const DELETE = `POST`;

export default class AJAX {
    method;
    route;
    url;
    params;

    static route(method, route, params) {
        const ajax = new AJAX();
        ajax.method = method;
        ajax.route = route;
        ajax.params = params;

        return ajax;
    }

    static url(method, url, params) {
        const ajax = new AJAX();
        ajax.method = method;
        ajax.url = url;
        ajax.params = params;

        return ajax;
    }

    json(body, callback) {
        if(typeof body === 'function') {
            callback = body;
            body = undefined;
        } else if(!(body instanceof FormData) && (typeof body === `object` || Array.isArray(body))) {
            body = JSON.stringify(body);
        }

        const url = this.route ? Routing.generate(this.route, this.params) : this.url;
        const config = {
            method: this.method,
            body
        };

        return fetch(url, config)
            .then(response => {
                if(response.url.endsWith(`/login`)) {
                    window.location.href = Routing.generate(`login`);
                } else {
                    return response.json();
                }
            })
            .then((json) => {
                treatFetchCallback(json);

                if(callback) {
                    callback(json);
                }
            })
            .catch(error => {
                console.error(error);
                Flash.add("danger", `Une erreur est survenue lors du traitement de votre requête par le serveur`);
            });
    }
}

function treatFetchCallback(json) {
    if(json.status === 500) {
        addFlashError(json);
        return;
    }

    if(json.success === false && json.message) {
        Flash.add("danger", json.message);
    } else if(json.success === true && json.message) {
        Flash.add("success", json.message);
    }

    if(json.reload === true) {
        $.fn.dataTable
            .tables({visible: true, api: true})
            .ajax.reload();
    }
}

function addFlashError(json) {
    console.error(json);
    Flash.add(Flash.DANGER, `Une erreur est survenue lors du traitement de votre requête par le serveur`);
}
