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
    static new($canva, config) {
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
        };
        let chart = new Chart($canva, config);
    }
}
