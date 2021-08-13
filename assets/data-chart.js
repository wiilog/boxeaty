import {
    Chart,
    BarElement,
    BarController,
    LinearScale,
    CategoryScale,
    LineController,
    LineElement,
    PointElement
} from 'chart.js';

Chart.register(
    BarElement,
    BarController,
    LinearScale,
    CategoryScale,
    LineController,
    LineElement,
    PointElement);

export default class ChartJS {
    static line($canva, config) {
        config.options = {
            elements: {
                line: {
                    borderWidth: 4,
                    lineTension: 0.5,
                },
                point: {
                    radius: 0
                }
            },
            scales: {
                yAxes: [{
                    display: true,
                    ticks: {
                        suggestedMin: 0,
                    },
                }]
            }
        };
        return new Chart($canva, config);
    }
}
