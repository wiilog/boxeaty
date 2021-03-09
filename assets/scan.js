import QrScanner from "qr-scanner";
import $ from "jquery";

QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

export default class Scan {

    static proceed(scanModal, {title, onScan}) {
        const $modalScan = scanModal.elem();
        $modalScan.find(`.scan-container-title`).text(title);

        scanModal.open();
        const qrScanner = new QrScanner($('.scan-element')[0], (result) => {
            qrScanner.stop();
            onScan(result)
                .then(() => {
                    scanModal.close();
                    qrScanner.destroy();
                })
                .catch(() => {
                    scanModal.close();
                    qrScanner.destroy();
                })
        });

        qrScanner.start().then(
            () => {
                qrScanner.hasCamera = true;
            },
            () => {
                qrScanner.hasCamera = false;
                const $scanContainer = $modalScan.find('.scan-container');
                $scanContainer.empty();
                $scanContainer.append(`
                    <div class="no-camera-found text-center mt-3">
                        <i class="fas fa-video-slash fa-3x"></i>
                        <p class="mt-2">Votre système ne dispose d'aucune caméra ou vous n'avez pas autorisé son accès.</p>
                    </div>
                `);
            }
        );

        $modalScan.on('hidden.bs.modal', function () {
            qrScanner.destroy();
        });
    }


}
