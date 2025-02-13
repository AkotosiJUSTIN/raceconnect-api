const ctx = document.getElementById("chart").getContext("2d");
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, "rgba(220, 38, 38, 0.8)");
gradient.addColorStop(1, "rgba(220, 38, 38, 0.2)");

new Chart(ctx, {
  type: "bar",
  data: {
    labels: [
      "Open Wheel Racing",
      "Endurance Racing",
      "GT Racing",
      "Rally Racing",
    ],
    datasets: [
      {
        label: "Participants",
        data: [170, 160, 90, 30],
        backgroundColor: gradient,
        borderRadius: {
          topLeft: 30,
          topRight: 30,
        },
      },
    ],
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
      },
    },
  },
});