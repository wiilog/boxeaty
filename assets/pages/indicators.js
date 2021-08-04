import '../app';

import $ from "jquery";
import AJAX from "../ajax";
import {processForm} from "../modal";
import ChartJS from "../data-chart";

$(function () {
    getIndicatorsValues();
    $('button.filter').on('click', function () {
        getIndicatorsValues();
    })
});

function getIndicatorsValues() {
    const $filters = $('.filters');
    const params = processForm($filters).asObject();
    AJAX.route(`GET`, `indicators_api`, params).json()
        .then((result) => {
            console.log(result);
            $('.total-boxes').text(result.containersUsed);
            $('.waste-avoided').text(result.wasteAvoided + " KG");
            $('.soft-mobility-total-distance').text(result.softMobilityTotalDistance + " KM");
            $('.motor-vehicles-total-distance').text(result.motorVehiclesTotalDistance + " KM");
            $('.return-rate').text(result.returnRate + " %");
            drawChart(result.chart);
        })
}

function drawChart(config) {
    let $container = $('.chart-container');
    $container.empty();
    $container.append('<canvas width="400" height="150"></canvas>');
    ChartJS.new($container.find('canvas').first(), JSON.parse(config));
}