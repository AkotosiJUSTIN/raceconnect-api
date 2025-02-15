document.addEventListener('DOMContentLoaded', () => {
    fetchMarketplaceItems();

    function fetchMarketplaceItems() {
        fetch('fetch_marketplace.php')
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
            // Use `favorite_count` instead of like count
            const favoriteCount = item.favorite_count || 0;

            // Generate star rating HTML (fallback to 0 if undefined)
            const rating = item.rating || 0;
            let starsHTML = '';
            for (let i = 1; i <= 5; i++) {
                starsHTML += `<box-icon name="star" ${i <= rating ? 'color="#FFD700"' : 'type="regular" color="#ccc"'}></box-icon>`;
            }

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
                <div class="product-interactions">
                    <div class="favorites-section">
                        <box-icon name="heart" color="#B91C1C"></box-icon>
                        <span>${favoriteCount} Favorites</span>
                    </div>
                </div>
            `;

            mainContent.appendChild(productCard);
        });
    }
});
