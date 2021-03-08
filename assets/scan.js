import QrScanner from "qr-scanner";
import $ from "jquery";
import AJAX from "./ajax";
import Flash from "./flash";

export default class Scan {

static proceed(scanModal, $select, type, msg) {
    QrScanner.WORKER_PATH = '/build/vendor/qr-scanner-worker.min.js';

    if(type === `boxes`) {
        scanModal.elem().find(`.scan-container-title`).text(`Scan de la Box`);
    } else {
        scanModal.elem().find(`.scan-container-title`).text(`Scan du ticket-consigne`);
    }

    scanModal.open()
    const qrScanner = new QrScanner($('.scan-element')[0], result => {
        if(result) {
            const url = Routing.generate(type === `boxes` ? `ajax_select_available_boxes` : `ajax_select_deposit_tickets`);
            AJAX.url(`GET`, url + `?term=${result}`).json(results => {
                const idk = results.results.find(r => r.text === result);

                if(idk) {
                    if(type === `boxes`) {
                        boxPrices[idk.text] = idk.price;
                    } else {
                        depositTicketPrices[idk.text] = idk.price;
                    }

                    let selectedOptions = $select.find(`option:selected`).map(function() {
                        return $(this).val();
                    }).toArray();

                    if($select.find(`option[value='${idk.id}']`).length === 0) {
                        let option = new Option(idk.text, idk.id, true, true);
                        $select.append(option);
                    }

                    selectedOptions.push(idk.id);
                    $select.val(selectedOptions).trigger("change");
                    Flash.add('success', msg.success);
                } else {
                    Flash.add('warning', msg.warning)
                }

                scanModal.close();
            });

            qrScanner.destroy();
        }
    });

    qrScanner.start().then(
        () => {
            qrScanner.hasCamera = true;
        },
        (error) => {
            console.log(error);
            qrScanner.hasCamera = false;
            const $scanContainer = $('#modal-scan').find('.scan-container');
            $scanContainer.empty();
            $scanContainer.append(`
                        <div class="no-camera-found text-center mt-3">
                            <i class="fas fa-video-slash fa-3x"></i>
                            <p class="mt-2">Votre système ne dispose d'aucune caméra ou vous n'avez pas autorisé son accès.</p>
                        </div>
                    `)
        }
    );

    $('#modal-scan').on('hidden.bs.modal', function() {
        qrScanner.destroy();
    });
}
}