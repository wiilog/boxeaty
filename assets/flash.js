export default class Flash {
    static INFO = `info`;
    static SUCCESS = `success`;
    static WARNING = `warning`;
    static DANGER = `danger`;

    static add(type, message) {
        $(`.alert-container`).append(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }
}
