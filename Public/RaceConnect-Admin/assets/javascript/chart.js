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
                        borderWidth: 2}],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false,
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
                                precision: 0
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
                                  // Break long labels into multiple lines
                                  if (window.innerWidth < 768) {
                                      // For mobile screens
                                      const words = label.split(' ');
                                      return words.map(word => 
                                          word.length > 8 ? word.substring(0, 6) + '...' : word
                                      );
                                  } else {
                                      // For larger screens
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
  const maxCount = Math.max(...counts);
  const minCount = Math.min(...counts);
  const highestCategories = categories.filter((_, index) => counts[index] === maxCount);
  const lowestCategories = categories.filter((_, index) => counts[index] === minCount);


  document.querySelector('.chart-highest-value').textContent = highestCategories.join(', ');
  document.querySelector('.chart-highest-number').textContent = maxCount;
  document.querySelector('.chart-lowest-value').textContent = lowestCategories.join(', ');
  document.querySelector('.chart-lowest-number').textContent = minCount;
}