import Flash from "./flash";

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
        } else if(typeof body === `object` || Array.isArray(body)) {
            body = JSON.stringify(body);
        }

        const url = this.route ? Routing.generate(this.route, this.params) : this.url;
        const config = {
            method: this.method,
            body
        };

        fetch(url, config)
            .then(response => response.json())
            .then(json => {
                if(json.status === 500) {
                    console.error(json);
                    Flash.add(Flash.DANGER, `Une erreur est survenue lors du traitement de votre requête par le serveur`);
                    return;
                }

                if(json.success === false && json.msg) {
                    Flash.add("danger", json.msg);
                } else if(json.success === true && json.msg) {
                    Flash.add("success", json.msg);
                }

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
