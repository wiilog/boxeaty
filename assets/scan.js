import QrScanner from "qr-scanner";
import Flash from "./flash";

QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

export default class Scan {
    static start(element, config = {}) {
        const $element = $(element);

        const scanner = new QrScanner(element, result => {
            if(!config.loop) {
                scanner.stop();
            }

            config.onScan(result).finally(() => scanner.destroy());
        });

        scanner.start().then(null, () => {
            Flash.add(`danger`, `Votre système ne dispose d'aucune caméra ou vous n'avez pas autorisé son accès`)
        });

        $element.closest(`.modal`).on('hidden.bs.modal', function() {
            scanner.destroy();
        });
    }

}
