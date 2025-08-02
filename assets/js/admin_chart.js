/**
 * Enhanced Professional Payment Chart
 * - Clean modern design
 * - Smooth animations
 * - Professional tooltips
 * - No unnecessary elements
 */
document.addEventListener('DOMContentLoaded', () => {
    const chartElementAdmin = document.getElementById('chartElementAdmin');
    if (!chartElementAdmin) return;

    const chartCanvas = document.getElementById('notesChart');
    const loadingElement = document.getElementById('chartLoading');
    
    // Create gradient for chart line
    const createGradient = (ctx) => {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)'); // indigo-500
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.05)');
        return gradient;
    };

    // Format currency tooltip
    const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(value);
    };

    const year = new Date().getFullYear();

    fetch(`/admin/dashboard/statistics?year=${year}`)
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(apiData => {
            const paymentsDataset = apiData.datasets.find(ds => 
    ds.label === (window.translations?.payments) || ds.label === 'Payments'
);
            loadingElement.style.display = 'none';
            chartCanvas.style.display = 'block';

            const ctx = chartCanvas.getContext('2d');
            
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: apiData.labels,
                    datasets: [{
                        label: window.translations?.monthlyPayments || 'Monthly Payments',
                        data: paymentsDataset ? paymentsDataset.data : [],
                        backgroundColor: createGradient(ctx),
                        borderColor: 'rgba(99, 102, 241, 1)', // indigo-500
                        borderWidth: 3,
                        tension: 0.4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: 'rgba(99, 102, 241, 1)',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            right: 20,
                            left: 10,
                            bottom: 10
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#6B7280',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 13,
                                    weight: '500'
                                },
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.95)',
                            titleColor: '#F9FAFB',
                            bodyColor: '#E5E7EB',
                            borderColor: '#1F2937',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            usePointStyle: true,
                            callbacks: {
                                label: (context) => {
                                    return `${context.dataset.label}: ${formatCurrency(context.raw)}`;
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
                                color: '#9CA3AF',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(243, 244, 246, 0.5)',
                                drawBorder: false,
                                borderDash: [4],
                                tickLength: 12
                            },
                            ticks: {
                                color: '#9CA3AF',
                                padding: 8,
                                callback: (value) => formatCurrency(value)
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            if (loadingElement) {
                loadingElement.innerHTML = `
                    <div class="p-4 bg-red-50 text-red-600 rounded-lg text-center">
                        <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="font-medium">${window.translations?.dataLoadingError || 'Data loading error'}</p>
                        <p class="text-sm mt-1">${window.translations?.networkError || error.message}</p>
                    </div>
                `;
            }
        });
});