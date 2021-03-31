import QrScanner from "qr-scanner";

QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

export default class Scan {
    static start(element, config = {}) {
        const $element = $(element);

        const scanner = new QrScanner(element, result => {
            if(!config.loop) {
                scanner.destroy();
            }

            config.onScan(result);
        }, undefined, undefined, `user`);

        scanner.start().then(null, () => {
            const $scanContainer = $element.closest(`.modal`).find('.scan-container');
            $scanContainer.empty();
            $scanContainer.append(`
                <div class="no-camera-found">
                    <i class="fas fa-video-slash fa-3x"></i>
                    <p>Votre système ne dispose d'aucune caméra ou vous n'avez pas autorisé son accès.</p>
                </div>
            `);
        });

        $element.closest(`.modal`).on('hidden.bs.modal', function() {
            scanner.destroy();
        });
    }

}
