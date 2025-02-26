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
    
        mainContent.innerHTML = '';
    
        if (!items || items.length === 0) {
            mainContent.innerHTML = `
                <div class="error-message">
                    <p>No reported posts found.</p>
                </div>
            `;
            return;
        }
    
        items.forEach(item => {
            const productCard = document.createElement('div');
            productCard.className = `product-card ${item.status === 'Hidden' ? 'blurred' : ''}`;
            productCard.dataset.itemId = item.id;
            productCard.dataset.status = item.status;
            
            productCard.innerHTML = `
                <div class="post-header">
                    <div class="user-info">
                        <span class="user-name">${item.title || 'Untitled Item'}</span>
                        <span class="post-time">${new Date(item.created_at).toLocaleString()}</span>
                    </div>
                    <div class="post-actions">
                        ${item.status === 'Hidden' 
                            ? `<button class="unhide-btn" onclick="unhideItem(${item.id})" title="Unhide Item">
                                <box-icon type='solid' name='show' color="white"></box-icon>
                               </button>`
                            : `<button onclick="hideItem(${item.id})" title="Hide Item">
                                <box-icon type='solid' name='hide' color="white"></box-icon>
                               </button>`
                        }
                        <button onclick="archiveItem(${item.id})" title="Archive Item">
                            <box-icon type='solid' name='archive-in' color="white"></box-icon>
                        </button>
                    </div>
                </div>
    
                <div class="report-info">
                    <span class="report-reason">Reason: ${item.report_reason || 'No reason provided'}</span>
                    <span class="report-date">Reported by: ${item.reporter_username || 'Unknown'}</span>
                    <span class="report-date">on ${item.reported_at ? new Date(item.reported_at).toLocaleString() : 'Unknown date'}</span>
                </div>
    
                <div class="scrollable-content ${item.status === 'Hidden' ? 'blurred' : ''}">
                    <div class="product-details">
                        <div class="product-price">₱${parseFloat(item.price).toFixed(2)}</div>
                        <div class="product-description">${item.description}</div>
                        ${item.image_urls && item.image_urls.length > 0 ? `
                            <div class="post-image">
                                <img src="${item.image_urls[0]}" alt="${item.title}">
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
    
            mainContent.appendChild(productCard);
        });
    }
    
    // Add action handlers
    window.hideItem = function(itemId) {
        fetch('fetch_api.php?action=hide_marketplace_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `item_id=${itemId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                fetchMarketplaceItems();
            } else {
                console.error('Error:', result.error);
            }
        })
        .catch(error => console.error('Error:', error));
    };
    
    window.unhideItem = function(itemId) {
        fetch('fetch_api.php?action=unhide_marketplace_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `item_id=${itemId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                fetchMarketplaceItems();
            } else {
                console.error('Error:', result.error);
            }
        })
        .catch(error => console.error('Error:', error));
    };
    
    window.archiveItem = function(itemId) {
        fetch('fetch_api.php?action=archive_marketplace_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `item_id=${itemId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                fetchMarketplaceItems();
            } else {
                console.error('Error:', result.error);
            }
        })
        .catch(error => console.error('Error:', error));
    };

    window.reportMarketplaceItem = function(itemId) {
        const data = new FormData();
        data.append('item_id', itemId);
        data.append('reporter_id', '1'); // Replace with actual reporter ID
        data.append('reason', 'Reported from marketplace');
    
        fetch('fetch_api.php?action=report_marketplace_item', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                fetchMarketplaceItems();
            } else {
                console.error('Error:', result.error);
            }
        })
        .catch(error => console.error('Error:', error));
    };
});
