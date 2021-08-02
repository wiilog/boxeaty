import {Chart, BarElement, BarController, LinearScale, CategoryScale} from 'chart.js';

Chart.register(BarElement, BarController, LinearScale, CategoryScale);

export default class ChartJS {
    static new($canva, config) {
        let chart = new Chart($canva, config)
    }
}