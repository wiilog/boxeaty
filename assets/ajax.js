import Flash from "./flash";

export default class AJAX {
    method;
    route;
    params;

    static for(method, route, params) {
        const ajax = new AJAX();
        ajax.method = method;
        ajax.route = route;
        ajax.params = params;

        return ajax;
    }

    async json(body) {
        if(typeof body === `object` || Array.isArray(body)) {
            body = JSON.stringify(body);
        }

        const url = Routing.generate(this.route, this.params);
        const config = {
            method: this.method,
            body
        };

        return await fetch(url, config)
            .then(response => response.json())
            .catch(_ => {
                console.error(`Format de données incorrect (JSON attendu)`);

                return {
                    success: false,
                    msg: `Une erreur est survenue lors du traitement de votre requête par le serveur`,
                }
            });
    }
}
