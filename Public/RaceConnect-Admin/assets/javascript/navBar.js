document.getElementById("userIcon").addEventListener("click", function () {
    const dropdownMenu = document.getElementById("dropdownMenu");
    dropdownMenu.classList.toggle("open");
});

const menuButton = document.getElementById("menuButton");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");

menuButton.addEventListener("click", () => {
    sidebar.classList.toggle("open");
    overlay.classList.toggle("hidden");
    document.body.classList.toggle("overflow-hidden");
});

overlay.addEventListener("click", () => {
    sidebar.classList.remove("open");
    overlay.classList.add("hidden");
    document.body.classList.remove("overflow-hidden");
});

// Close sidebar on escape key press
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        sidebar.classList.remove("open");
        overlay.classList.add("hidden");
        document.body.classList.remove("overflow-hidden");
    }
});