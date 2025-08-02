/**
 * This script initializes and renders a responsive line chart using Chart.js
 * to display monthly payment totals for a given year.
 * 
 * It fetches payment data from the backend endpoint `/dashboard/statistics`
 * with a query parameter `year` (default is the current year).
 * 
 * The chart is displayed only if:
 * - The container element with id "chartElement" exists,
 * - And the fetched data contains at least one payment value > 0.
 * 
 * UI elements controlled by this script:
 * - #chartLoading: a loading spinner shown while fetching data,
 * - #notesChart: the canvas element where the chart is drawn,
 * - #noChartData: a placeholder message shown if there is no data.
 * 
 * Chart.js options are configured for a clean, responsive line chart with
 * TailwindCSS-friendly colors and smooth animations.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Select the main container that wraps the chart - exit if not found (chart not needed)
    const chartElement = document.getElementById('chartElement');
    if (!chartElement) return;

    // Cache references to key DOM elements
    const chartCanvas = document.getElementById('notesChart');
    const loadingElement = document.getElementById('chartLoading');
    const noDataElement = document.getElementById('noChartData');

    // Determine the year to display data for (current year by default)
    const year = new Date().getFullYear();

    // Fetch payment data for the selected year from backend API
    fetch(`/dashboard/statistics?year=${year}`)
        .then(response => {
            // Check if HTTP response status is OK (status 200-299)
            if (!response.ok) throw new Error('Network error');
            // Parse response JSON body
            return response.json();
        })
        .then(apiData => {
            // Find the dataset labeled "Payments" in the fetched data
            const paymentsDataset = apiData.datasets.find(ds => ds.label === 'Payments');
            // Check if the payments dataset contains at least one positive value
            const hasData = paymentsDataset && paymentsDataset.data.some(value => value > 0);

            // Hide loading spinner as data has arrived
            loadingElement.style.display = 'none';

            if (!hasData) {
                // No data: show the "No data" placeholder and hide the canvas
                noDataElement.classList.remove('hidden');
                chartCanvas.style.display = 'none';
                return;
            } else {
                // Data exists: hide the "No data" message and show the chart canvas
                noDataElement.classList.add('hidden');
                chartCanvas.style.display = 'block';
            }

            // Chart.js configuration object
            const config = {
                type: 'line',
                data: {
                    labels: apiData.labels, // e.g. ['January', 'February', ...]
                    datasets: [
                        {
                            label: 'Payments',      // Dataset label shown in tooltip and legend
                            data: paymentsDataset.data,  // Array of payment amounts per month
                            backgroundColor: 'rgba(58, 138, 192, 0.1)', // Light blue fill
                            borderColor: 'rgba(58, 138, 192, 1)',       // Blue line
                            borderWidth: 2,
                            tension: 0.3,               // Smooth line curves
                            pointBackgroundColor: 'rgba(58, 138, 192, 1)',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true                  // Fill area under the line
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
                        legend: { display: true },
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
                                // Custom tooltip label formatting
                                label: (context) => `${context.dataset.label}: ${context.formattedValue}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: {
                                color: '#6B7280', // Tailwind gray-500
                                font: { family: "'Inter', sans-serif", size: 12 }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#F3F4F6', // Tailwind gray-100
                                drawBorder: false,
                                borderDash: [4],
                                tickLength: 12
                            },
                            ticks: {
                                color: '#6B7280',
                                padding: 8,
                                // Show only integer ticks on Y axis
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

            // Initialize the Chart.js chart instance on the canvas element
            new Chart(chartCanvas, config);
        })
        .catch(error => {
            // On error, log to console and display user-friendly message in loading element
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
