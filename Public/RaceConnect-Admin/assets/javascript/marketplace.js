document.addEventListener('DOMContentLoaded', () => {
    fetchMarketplaceItems();

    // Simulate fetching marketplace items
    function fetchMarketplaceItems() {
        // Simulated API response (mock data)
        const mockData = {
            items: [
                {
                    id: 1,
                    seller_id: 101,
                    title: "Placeholder item",
                    description: "Lorem ipsum lorem ipsum lorem ipsum",
                    price: 99.99,
                    category: "Electronics",
                    image_url: "https://via.placeholder.com/300x200?text=Headphones",
                    favorite_count: 15,
                    status: "available",
                    created_at: "2023-01-01T12:00:00Z",
                    updated_at: "2023-01-02T15:30:00Z"
                },
                {
                    id: 2,
                    seller_id: 102,
                    title: "Placeholder item2",
                    description: "Lorem ipsum lorem ipsum lorem ipsum",
                    price: 149.99,
                    category: "Wearables",
                    image_url: "https://via.placeholder.com/300x200?text=Smartwatch",
                    favorite_count: 25,
                    status: "available",
                    created_at: "2023-01-03T09:00:00Z",
                    updated_at: "2023-01-04T18:00:00Z"
                }
            ],
            likes: [
                {
                    id: 1,
                    user_id: 201,
                    marketplace_item_id: 1,
                    created_at: "2023-01-01T12:10:00Z"
                },
                {
                    id: 2,
                    user_id: 202,
                    marketplace_item_id: 1,
                    created_at: "2023-01-01T12:15:00Z"
                },
                {
                    id: 3,
                    user_id: 203,
                    marketplace_item_id: 2,
                    created_at: "2023-01-03T09:10:00Z"
                }
            ]
        };

        // Simulate a successful fetch response
        setTimeout(() => {
            console.log('Mocked marketplace items:', mockData); // Debugging statement
            populateMarketplace(mockData.items, mockData.likes);
        }, 500); // Simulate a 500ms network delay
    }

    // Populate the marketplace with product cards
    function populateMarketplace(items, likes) {
        const mainContent = document.getElementById('mainContent');
        mainContent.innerHTML = ''; // Clear existing content
    
        items.forEach(item => {
            // Count the number of likes for the current item
            const likeCount = likes.filter(like => like.marketplace_item_id === item.id).length;
    
            // Generate star rating HTML (assuming `item.rating` contains the average rating)
            const rating = item.rating || 0; // Default to 0 if no rating exists
            const maxStars = 5;
            let starsHTML = '';
            for (let i = 1; i <= maxStars; i++) {
                if (i <= rating) {
                    starsHTML += '<box-icon name="star" color="#FFD700"></box-icon>'; // Filled star
                } else {
                    starsHTML += '<box-icon name="star" type="regular" color="#ccc"></box-icon>'; // Empty star
                }
            }
    
            // Create a product card
            const productCard = document.createElement('div');
            productCard.className = 'product-card';
            productCard.innerHTML = `
                <div class="product-image">
                    <img src="${item.image_url}" alt="${item.title}">
                </div>
                <div class="product-details">
                    <div class="product-title">${item.title}</div>
                    <!--- To be fixed
                    <div class="post-actions">
                        <button class="edit-btn"><box-icon size="sm" name='edit' color="white"></box-icon></button>
                        <button class="unsee-btn"><box-icon size="sm" type='solid' name='low-vision' color="white"></box-icon></button>
                        <button class="delete-btn"><box-icon size="sm" type='solid' name='trash' color="white"></box-icon></button>
                    </div>
                    --->
                    <div class="product-price">$${item.price.toFixed(2)}</div>
                    <div class="product-description">${item.description}</div>
                </div>
                <div class="product-interactions">
                    <div class="likes-section">
                        <box-icon name="heart" color="#B91C1C"></box-icon>
                        <span>${likeCount} Likes</span>
                    </div>
                    <div class="rating-section">
                        ${starsHTML}
                    </div>
                </div>
            `;
            mainContent.appendChild(productCard);
        });
    }
});