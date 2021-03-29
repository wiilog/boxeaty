export default class Flash {
    static INFO = `info`;
    static SUCCESS = `success`;
    static WARNING = `warning`;
    static DANGER = `danger`;

    static add(type, message, unique = false) {
        const $alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        const $container = $('.alert-container');
        if(unique) {
            $container.empty();
        }

        $alert.appendTo(`.alert-container`)
        $container.css('z-index', 10000);

        setTimeout(() => $alert.fadeOut(500, function () {
            $(this).remove();
        }), 5000);
    }
}
