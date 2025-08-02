/**
 * @fileoverview
 * This script renders a responsive line chart on the dashboard using Chart.js.
 * It fetches statistical data from the backend via the `/dashboard/statistics` endpoint
 * and displays it in a visually styled line chart.
 * 
 * The chart is only initialized if a container element with the ID `chartElement` exists in the DOM.
 * 
 * Chart design includes:
 * - Two datasets (e.g. candidate scores, offer requirements)
 * - Custom tooltips
 * - Tailwind-style colors
 * - Smooth animations
 * 
 * Requirements:
 * - The following HTML elements must exist:
 *   - `<div id="chartElement">`: The wrapper that indicates chart initialization is needed
 *   - `<canvas id="notesChart">`: The canvas element where the chart is rendered
 *   - `<div id="chartLoading">`: A loading indicator shown while data is fetched
 */

document.addEventListener('DOMContentLoaded', () => {
    /** @type {HTMLElement|null} The chart container element used to determine whether to initialize the chart */
    const chartElement = document.getElementById('chartElement');
    if (!chartElement) return;

    /** @type {HTMLCanvasElement} The canvas element where the chart is drawn */
    const chartCanvas = document.getElementById('notesChart');

    /** @type {HTMLElement} The loading element shown while the chart data is loading */
    const loadingElement = document.getElementById('chartLoading');

    // Fetch data from the backend API endpoint
    fetch('/dashboard/statistics')
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(apiData => {
            /**
             * Chart.js configuration object
             * @type {import('chart.js').ChartConfiguration}
             */
            const config = {
                type: 'line',
                data: {
                    labels: apiData.labels,
                    datasets: [
                        {
                            label: apiData.datasets[0].label,
                            data: apiData.datasets[0].data,
                            backgroundColor: 'rgba(58, 138, 192, 0.1)',
                            borderColor: 'rgba(58, 138, 192, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(58, 138, 192, 1)',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true
                        },
                        {
                            label: apiData.datasets[1].label,
                            data: apiData.datasets[1].data,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: { top: 20, right: 20, left: 10, bottom: 10 }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            titleColor: '#F9FAFB',
                            bodyColor: '#E5E7EB',
                            borderColor: '#1F2937',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            usePointStyle: true,
                            callbacks: {
                                /**
                                 * Custom tooltip label callback
                                 * @param {Object} context - Tooltip context object
                                 * @returns {string} Formatted tooltip string
                                 */
                                label: (context) => {
                                    return `${context.dataset.label}: ${context.formattedValue}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6B7280',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#F3F4F6',
                                drawBorder: false,
                                borderDash: [4],
                                tickLength: 12
                            },
                            ticks: {
                                color: '#6B7280',
                                padding: 8,
                                stepSize: 1,
                                callback: (value) => Number.isInteger(value) ? value : ''
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeOutQuart'
                    }
                }
            };

            // Hide the loading element and show the chart
            loadingElement.style.display = 'none';
            chartCanvas.style.display = 'block';
            const hasData =
            apiData.datasets &&
            apiData.datasets.length === 2 &&
            apiData.datasets[0].data.length > 0 &&
            apiData.datasets[1].data.length > 0;

        if (!hasData) {
            // No data: show fallback message
            loadingElement.style.display = 'none';
            document.getElementById('noChartData').classList.remove('hidden');
            return;
        }
            // Render the chart using Chart.js
            new Chart(chartCanvas, config);
        })
        .catch(error => {
            console.error('Error:', error);
            loadingElement.innerHTML = `
                <div class="p-4 bg-red-50 text-red-600 rounded text-center">
                    <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Error loading chart data
                </div>
            `;
        });
});
