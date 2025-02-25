document.addEventListener('DOMContentLoaded', () => {
    fetchMarketplaceItems();

    function fetchMarketplaceItems() {
        fetch('fetch_api.php?action=fetch_marketplace')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Fetched marketplace items:', data);
                populateMarketplace(data.items);
            })
            .catch(error => {
                console.error('Error fetching marketplace items:', error);
            });
    }

    function populateMarketplace(items) {
        const mainContent = document.getElementById('mainContent');
        if (!mainContent) {
            console.error('Error: #mainContent element not found.');
            return;
        }

        mainContent.innerHTML = ''; // Clear existing content

        items.forEach(item => {
            // Create product card
            const productCard = document.createElement('div');
            productCard.className = 'product-card';
            productCard.innerHTML = `
                <div class="product-details">
                    <div class="product-title">${item.title}</div>
                    <div class="product-price">₱${parseFloat(item.price).toFixed(2)}</div>
                    <div class="product-description">${item.description}</div>
                    <div class="product-image">
                        <img src="${item.image_url}" alt="${item.title}">
                    </div>
                </div>
            `;

            mainContent.appendChild(productCard);
        });
    }
});
