document.addEventListener('DOMContentLoaded', function () {
    // Initialize all custom select boxes
    initializeCustomSelects();
});

/**
 * Initializes all custom select boxes on the page.
 */
function initializeCustomSelects() {
    const customSelects = document.getElementsByClassName("custom-select");

    for (let i = 0; i < customSelects.length; i++) {
        const selectElement = customSelects[i].getElementsByTagName("select")[0];
        createCustomSelect(customSelects[i], selectElement);
    }

    // Close dropdowns when clicking outside
    document.addEventListener("click", closeAllDropdowns);
}

/**
 * Creates a custom select box from a native <select> element.
 * @param {HTMLElement} container - The container element with class "custom-select".
 * @param {HTMLSelectElement} selectElement - The native <select> element.
 */
function createCustomSelect(container, selectElement) {
    const options = Array.from(selectElement.options);

    // Create the selected item display
    const selectedItem = createSelectedItem(options[selectElement.selectedIndex].text);
    container.appendChild(selectedItem);

    // Create the dropdown items container
    const dropdownContainer = createDropdownContainer(options, selectElement, selectedItem);
    container.appendChild(dropdownContainer);

    // Add click event to toggle dropdown visibility
    selectedItem.addEventListener("click", function (e) {
        e.stopPropagation(); // Prevent click from propagating to the document
        toggleDropdownVisibility(dropdownContainer, selectedItem);
    });
}

/**
 * Creates the selected item display (the visible part of the custom select).
 * @param {string} text - The text to display as the selected value.
 * @returns {HTMLElement} - The created DIV element.
 */
function createSelectedItem(text) {
    const div = document.createElement("DIV");
    div.setAttribute("class", "select-selected");
    div.textContent = text;
    return div;
}

/**
 * Creates the dropdown container with all the options.
 * @param {HTMLOptionElement[]} options - The array of <option> elements.
 * @param {HTMLSelectElement} selectElement - The native <select> element.
 * @param {HTMLElement} selectedItem - The selected item display element.
 * @returns {HTMLElement} - The created DIV element for the dropdown.
 */
function createDropdownContainer(options, selectElement, selectedItem) {
    const dropdownContainer = document.createElement("DIV");
    dropdownContainer.setAttribute("class", "select-items select-hide");

    options.forEach((option, index) => {
        if (index === 0) return; // Skip the first option (usually a placeholder)

        const optionDiv = document.createElement("DIV");
        optionDiv.textContent = option.text;

        // Handle option selection
        optionDiv.addEventListener("click", function () {
            handleOptionSelection(optionDiv, selectElement, selectedItem, dropdownContainer);
        });

        dropdownContainer.appendChild(optionDiv);
    });

    return dropdownContainer;
}

/**
 * Handles the selection of an option in the dropdown.
 * @param {HTMLElement} clickedOption - The clicked option DIV.
 * @param {HTMLSelectElement} selectElement - The native <select> element.
 * @param {HTMLElement} selectedItem - The selected item display element.
 * @param {HTMLElement} dropdownContainer - The dropdown container element.
 */
function handleOptionSelection(clickedOption, selectElement, selectedItem, dropdownContainer) {
    const options = Array.from(selectElement.options);

    // Update the native <select> element's value
    options.forEach((option, index) => {
        if (option.text === clickedOption.textContent) {
            selectElement.selectedIndex = index;
        }
    });

    // Update the displayed selected item
    selectedItem.textContent = clickedOption.textContent;

    // Remove "selected" styling from other options
    const previouslySelected = dropdownContainer.getElementsByClassName("same-as-selected");
    Array.from(previouslySelected).forEach(el => el.classList.remove("same-as-selected"));

    // Add "selected" styling to the clicked option
    clickedOption.classList.add("same-as-selected");

    // Close the dropdown
    closeDropdown(dropdownContainer, selectedItem);
}

/**
 * Toggles the visibility of a dropdown.
 * @param {HTMLElement} dropdownContainer - The dropdown container element.
 * @param {HTMLElement} selectedItem - The selected item display element.
 */
function toggleDropdownVisibility(dropdownContainer, selectedItem) {
    closeAllDropdowns();
    dropdownContainer.classList.toggle("select-hide");
    selectedItem.classList.toggle("select-arrow-active");
}

/**
 * Closes a specific dropdown.
 * @param {HTMLElement} dropdownContainer - The dropdown container element.
 * @param {HTMLElement} selectedItem - The selected item display element.
 */
function closeDropdown(dropdownContainer, selectedItem) {
    dropdownContainer.classList.add("select-hide");
    selectedItem.classList.remove("select-arrow-active");
}

/**
 * Closes all open dropdowns except the one associated with the clicked element.
 */
function closeAllDropdowns() {
    const dropdownContainers = document.getElementsByClassName("select-items");
    const selectedItems = document.getElementsByClassName("select-selected");

    Array.from(dropdownContainers).forEach(container => {
        container.classList.add("select-hide");
    });

    Array.from(selectedItems).forEach(item => {
        item.classList.remove("select-arrow-active");
    });
}