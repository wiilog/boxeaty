import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import {processForm} from "../modal";
import ChartJS from "../data-chart";
import html2pdf from "html2pdf.js";

$(function () {
    getIndicatorsValues(true);
    let canExport = false;
    $('button.filter').on('click', function () {
        getIndicatorsValues(false);
    })

    $('.exportPDF').click(function () {
        if (canExport) {
            getIndicatorsValues();
            setTimeout(() => printPDF(), 1000);
        }
    });

    function getIndicatorsValues(isFirstLoad) {
        const $filters = $('.filters');
        const params = !isFirstLoad ? processForm($filters) : true;
        if (params) {
            canExport = true;
            AJAX.route(`GET`, `indicators_api`, isFirstLoad ? {} : params.asObject()).json()
                .then((result) => {
                    $('.total-boxes').text(result.containersUsed);
                    $('.waste-avoided').text(result.wasteAvoided + " KG");
                    $('.soft-mobility-total-distance').text(result.softMobilityTotalDistance + " KM");
                    $('.motor-vehicles-total-distance').text(result.motorVehiclesTotalDistance + " KM");
                    $('.return-rate').text(result.returnRate + " %");
                    drawChart(result.chart, params);
                    $('#rendered').val(1);
                });
        } else {
            canExport = false;
        }
    }

    function drawChart(config, params) {
        let $container = $('#indicatorsChart');
        const data = typeof params === 'object' ? params.asObject() : {};

        if (data.client && data.from && data.to) {
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
    let img = "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";
    let element = document.getElementById('indicators');
    let opt = {
        margin: [30, 0, 0, 0],
        filename: fileName,
        image: {type: 'jpeg', quality: 1},
        jsPDF: {unit: 'mm', format: [240, 400], orientation: 'landscape'},

    };

    html2pdf().from(element).set(opt).toPdf().get('pdf').then(function (pdf) {

        pdf.addImage(img, "PNG", 10, 2, 50, 25);
        pdf.text(200, 10, "Du :");
        pdf.text(211, 10, startDate);
        pdf.text(244, 10, "Au :");
        pdf.text(255, 10, endDate);
        pdf.text(200, 16, client);
    }).save();
}

function convertDateToFrenchFormat(date) {
    let dateToConvert = new Date(date);
    const options = {day: 'numeric', month: 'numeric', year: 'numeric'};
    return (dateToConvert.toLocaleDateString('fr-FR', options))
}
