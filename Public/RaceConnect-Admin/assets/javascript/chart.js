document.addEventListener('DOMContentLoaded', () => {
    // Get the chart context
    const ctx = document.getElementById("chart").getContext("2d");
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(220, 38, 38, 0.8)");
    gradient.addColorStop(1, "rgba(220, 38, 38, 0.2)");

    // Fetch data and create chart
    fetch('fetch_api.php?action=fetch_dashboard_data')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch data');
            }

            // Update most popular category card
            const mostPopularCard = document.querySelector('.stat-number');
            if (mostPopularCard && data.mostPopular) {
                mostPopularCard.textContent = data.mostPopular.category;
            }

            // Create the chart
            const chart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: data.categories,
                    datasets: [{
                        label: "Posts",
                        data: data.counts,
                        backgroundColor: gradient,
                        borderColor: "rgba(220, 38, 38, 1)",
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.parsed.y} posts`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Posts by Category',
                            color: '#111827',
                            font: {
                                size: 24,
                                weight: 'bold'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                precision: 0,
                                callback: function(value) {
                                    return value + ' posts';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                callback: function(value) {
                                    const label = this.getLabelForValue(value);
                                    if (window.innerWidth < 768) {
                                        const words = label.split(' ');
                                        return words.map(word => 
                                            word.length > 8 ? word.substring(0, 6) + '...' : word
                                        );
                                    } else {
                                        return label.length > 15 ? 
                                            label.split(' ').map(word => 
                                                word.length > 10 ? word.substring(0, 8) + '...' : word
                                            ) : 
                                            label;
                                    }
                                },
                                font: {
                                    size: function() {
                                        return window.innerWidth < 768 ? 10 : 12;
                                    }
                                }
                            }
                        }
                    }
                }
            });

            // Update highest/lowest info
            updateChartDetails(data.categories, data.counts);
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });
});

function updateChartDetails(categories, counts) {
    // Find the highest count and its index
    const maxCount = Math.max(...counts);
    const maxIndex = counts.indexOf(maxCount);
    
    // Update the stat card
    const popularCategoryElement = document.getElementById('popularCategory');
    if (popularCategoryElement && maxCount > 0) {
        popularCategoryElement.textContent = categories[maxIndex];
    }

    // Update other chart details
    document.querySelector('.chart-highest-value').textContent = categories[maxIndex] || 'None';
    document.querySelector('.chart-highest-number').textContent = maxCount + ' posts';
    
    // Find lowest (excluding zero counts if possible)
    const nonZeroCounts = counts.filter(count => count > 0);
    const minCount = nonZeroCounts.length > 0 ? Math.min(...nonZeroCounts) : 0;
    const minIndex = counts.indexOf(minCount);
    
    document.querySelector('.chart-lowest-value').textContent = minCount > 0 ? categories[minIndex] : 'None';
    document.querySelector('.chart-lowest-number').textContent = minCount + ' posts';
}