import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import {processForm} from "../modal";
import ChartJS from "../data-chart";
import html2pdf from "html2pdf.js";

let LOGO_PNG = null;

$(function() {
    toDataUrl(`/images/logo.png`, data => LOGO_PNG = data);

    getIndicatorsValues(true);
    $('button.filter').on('click', function() {
        getIndicatorsValues(false);
    })

    $('.exportPDF').click(function() {
        getIndicatorsValues().then(() => printPDF());
    });

    function getIndicatorsValues(isFirstLoad) {
        const $filters = $('.filters');
        const params = !isFirstLoad ? processForm($filters) : true;

        return new Promise((resolve, reject) => {
            if(params) {
                AJAX.route(`GET`, `indicators_api`, isFirstLoad ? {} : params.asObject()).json()
                    .then((result) => {
                        $('.total-boxes').text(result.containersUsed);
                        $('.waste-avoided').text(result.wasteAvoided + " KG");
                        $('.soft-mobility-total-distance').text(result.softMobilityTotalDistance + " KM");
                        $('.motor-vehicles-total-distance').text(result.motorVehiclesTotalDistance + " KM");
                        $('.return-rate').text(result.returnRate + " %");
                        drawChart(result.chart, params);

                        resolve();
                    });
            } else {
                reject();
            }
        });
    }

    function drawChart(config, params) {
        let $container = $('#indicatorsChart');
        const data = typeof params === 'object' ? params.asObject() : {};

        if(data.client && data.from && data.to) {
            $container.replaceWith('<canvas id="indicatorsChart" width="400" height="150" data-rendered="1"></canvas>');
            ChartJS.line($('#indicatorsChart'), JSON.parse(config));
        } else {
            $container.replaceWith(`
                <div id="indicatorsChart" class="d-flex flex-column align-items-center h-100 justify-content-center">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <span>Un couple de filtres client/dates est nécessaire afin d'afficher le graphique</span>
                </div>
            `);
        }
    }
})

function printPDF() {
    let client = $('select[name="client"]').text().trim();
    let startDate = convertDateToFrenchFormat($('input[name="from"]').val());
    let endDate = convertDateToFrenchFormat($('input[name="to"]').val());
    const fileName = `export-indicateurs-${client}-${startDate}-${endDate}`;
    let element = document.getElementById('indicators');
    let opt = {
        margin: [30, 10, 10, 10],
        filename: fileName,
        image: {type: 'jpeg', quality: 1},
        jsPDF: {unit: 'mm', format: [240, 400], orientation: 'landscape'},

    };

    html2pdf().from(element).set(opt).toPdf().get('pdf').then(function(pdf) {
        pdf.addImage(LOGO_PNG, "PNG", 10, 4, 50, 20);

        pdf.setFontSize(14);
        pdf.text(390, 10, `Du ${startDate} au ${endDate}`, `right`);
        pdf.setFontSize(18);
        pdf.text(390, 16, client, `right`);
    }).save();
}

function convertDateToFrenchFormat(date) {
    let dateToConvert = new Date(date);
    const options = {day: 'numeric', month: 'numeric', year: 'numeric'};
    return (dateToConvert.toLocaleDateString('fr-FR', options))
}

function toDataUrl(url, callback) {
    AJAX.url(`GET`, url).raw().then(response => {
        const reader = new FileReader();
        reader.onloadend = function() {
            callback(reader.result.split(`,`)[1]);
        };

        response.blob().then(blob => reader.readAsDataURL(blob));
    })
}
