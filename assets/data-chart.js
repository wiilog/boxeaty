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
    static line($canva, config, drawCallback) {
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
                y: {
                    display: true,
                    beginAtZero: true,
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            animation: {
                duration: 100,
                onComplete() {
                    if(drawCallback) {
                        drawCallback();
                    }
                }
            }
        };

        return new Chart($canva, config);
    }
}
