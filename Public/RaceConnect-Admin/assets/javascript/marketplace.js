// Marketplace.js
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
                    <p>No reported items found.</p>
                </div>
            `;
            return;
        }
    
        items.forEach(item => {
            const itemCard = document.createElement('div');
            // Add data attribute for item ID
            itemCard.setAttribute('data-item-id', item.id);
            
            // Check if this item should be highlighted
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('item_id');
            const shouldHighlight = highlightId === item.id.toString();
            
            // Add classes including highlight if needed
            itemCard.className = `product-card ${item.status === 'Hidden' ? 'blurred' : ''} ${shouldHighlight ? 'highlighted' : ''}`;
    
            // If this is the highlighted item, scroll to it
            if (shouldHighlight) {
                setTimeout(() => {
                    itemCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    console.log('Highlighting item:', item.id); // Debug log
                }, 100);
            }
    
            // Rest of your existing item card HTML
            itemCard.innerHTML = `
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
                    <span class="reporter-name">Reported by: ${item.reporter_username || 'Unknown'}</span>
                    <span class="report-reason">Reason: ${item.report_reason || 'No reason provided'}</span>
                    <span class="report-count">Reports: ${item.pending_report_count || 0}</span>
                    <span class="report-date">on ${item.reported_at ? new Date(item.reported_at).toLocaleString() : 'Unknown date'}</span>
                </div>
    
                <div class="scrollable-content ${item.status === 'Hidden' ? 'blurred' : ''}">
                    <div class="product-details">
                        <div class="product-price">₱${parseFloat(item.price).toFixed(2)}</div>
                        <div class="product-description">${item.description}</div>
                    </div>
                </div>
                ${item.image_urls && item.image_urls.length > 0
                    ? createCarousel(item.image_urls, `item-${item.id}`)
                    : ''}
            `;
    
            mainContent.appendChild(itemCard);
    
            // Add blur class to specific elements if status is Hidden
            if (item.status === 'Hidden') {
                const scrollableContent = itemCard.querySelector('.scrollable-content');
                const carousel = itemCard.querySelector('.carousel');
                
                if (scrollableContent) {
                    scrollableContent.classList.add('blurred');
                }
                if (carousel) {
                    carousel.classList.add('blurred');
                }
            }
    
            if (item.image_urls && item.image_urls.length > 0) {
                initCarousel(`item-${item.id}`);
            }
        });
    
        // Call removeHighlightAfterDelay after populating
        removeHighlightAfterDelay();
    }

    function removeHighlightAfterDelay() {
        const urlParams = new URLSearchParams(window.location.search);
        const itemId = urlParams.get('item_id');
        
        if (itemId) {
            const highlightedElement = document.querySelector(`[data-item-id="${itemId}"]`);
            if (highlightedElement) {
                console.log('Found highlighted element'); // Debug log
                
                // Remove highlight after delay
                setTimeout(() => {
                    highlightedElement.classList.remove('highlighted');
                    
                    // Update URL without the highlight parameter
                    const newUrl = window.location.pathname;
                    window.history.replaceState({}, '', newUrl);
                    
                    console.log('Removed highlight'); // Debug log
                }, 5000);
            }
        }
    }

    // Add this to the DOMContentLoaded event listener
    document.addEventListener('DOMContentLoaded', () => {
        // ... existing code ...
        removeHighlightAfterDelay();
    });

    // Add this function to both marketplace.js and user-posts.js
    function createCarousel(images, containerId) {
        if (!images || images.length === 0) return '';
        
        const carouselHtml = `
            <div class="carousel" id="carousel-${containerId}">
                <div class="carousel-inner">
                    ${images.map((img, index) => `
                        <div class="carousel-item ${index === 0 ? 'active' : ''}" data-index="${index}">
                            <img src="${img}" alt="Image ${index + 1}">
                        </div>
                    `).join('')}
                </div>
                ${images.length > 1 ? `
                    <button class="carousel-control carousel-prev">
                        <box-icon name='chevron-left' color="white"></box-icon>
                    </button>
                    <button class="carousel-control carousel-next">
                        <box-icon name='chevron-right' color="white"></box-icon>
                    </button>
                    <div class="carousel-indicators">
                        ${images.map((_, index) => `
                            <div class="carousel-indicator ${index === 0 ? 'active' : ''}" data-index="${index}"></div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;

        return carouselHtml;
    }

    function initCarousel(containerId) {
        const carousel = document.querySelector(`#carousel-${containerId}`);
        if (!carousel) return;

        const items = carousel.querySelectorAll('.carousel-item');
        const prevBtn = carousel.querySelector('.carousel-prev');
        const nextBtn = carousel.querySelector('.carousel-next');
        const indicators = carousel.querySelectorAll('.carousel-indicator');
        let currentIndex = 0;

        function showItem(index) {
            items.forEach(item => item.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            items[index].classList.add('active');
            indicators[index].classList.add('active');
            currentIndex = index;
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                const newIndex = (currentIndex - 1 + items.length) % items.length;
                showItem(newIndex);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const newIndex = (currentIndex + 1) % items.length;
                showItem(newIndex);
            });
        }

        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => showItem(index));
        });
    }
    
    // Add action handlers
    window.hideItem = function(itemId) {
        Swal.fire({
            title: 'Hide Item',
            text: 'Are you sure you want to hide this marketplace item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('fetch_api.php?action=hide_marketplace_item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ item_id: itemId })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: 'Hidden!',
                            text: 'The item has been hidden.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            const productCard = document.querySelector(`[data-item-id="${itemId}"]`);
                            if (productCard) {
                                productCard.classList.add('blurred');
                                productCard.dataset.status = 'Hidden';
                                
                                const scrollableContent = productCard.querySelector('.scrollable-content');
                                const carousel = productCard.querySelector('.carousel');
                                
                                if (scrollableContent) {
                                    scrollableContent.classList.add('blurred');
                                }
                                if (carousel) {
                                    carousel.classList.add('blurred');
                                }
                            }
                            fetchMarketplaceItems();
                        });
                    }else {
                        throw new Error(result.error || 'Failed to hide item');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error!',
                        text: error.message,
                        icon: 'error'
                    });
                });
            }
        });
    };

    window.unhideItem = function(itemId) {
        Swal.fire({
            title: 'Unhide Item',
            text: 'Are you sure you want to unhide this marketplace item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('fetch_api.php?action=unhide_marketplace_item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ item_id: itemId })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: 'Unhidden!',
                            text: 'The item has been unhidden.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            const productCard = document.querySelector(`[data-item-id="${itemId}"]`);
                            if (productCard) {
                                productCard.remove();
                            }
                            fetchMarketplaceItems();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: result.error || 'Failed to unhide item',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to unhide item',
                        icon: 'error'
                    });
                });
            }
        });
    };

    const style = document.createElement('style');
    style.textContent = `
        .swal2-input-group {
            position: relative;
        }
        .toggle-password-btn {
            position: absolute;
            right: 30px;
            top: 60%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-btn:hover {
            opacity: 0.8;
        }
    `;
    document.head.appendChild(style);

    window.archiveItem = function(itemId) {
        Swal.fire({
            title: 'Archive Item',
            text: 'Please enter your password to confirm this action',
            html: `
                <div class="swal2-input-group">
                    <input type="password" id="swal-password" class="swal2-input" placeholder="Enter your password">
                    <button type="button" class="toggle-password-btn" onclick="toggleSwalPassword()">
                        <box-icon name='show' color="#374151"></box-icon>
                    </button>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm Archive',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const password = document.getElementById('swal-password').value;
                    if (!password) {
                        Swal.showValidationMessage('Password is required');
                        return false;
                    }
                    
                    const response = await fetch('fetch_api.php?action=archive_marketplace_item', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            item_id: itemId,
                            password: password
                        })
                    });

                    const result = await response.json();
                    if (!result.success) {
                        Swal.showValidationMessage(result.error || 'Failed to archive item');
                        return false;
                    }
                    return result;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Success',
                    text: 'Item has been archived',
                    icon: 'success',
                    timer: 1500
                }).then(() => {
                    fetchMarketplaceItems();
                    if (Math.random() < 0.1) {
                        checkCleanup();
                    }
                });
            }
        });
    };

    window.toggleSwalPassword = function() {
        const passwordInput = document.getElementById('swal-password');
        const toggleBtn = document.querySelector('.toggle-password-btn box-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.setAttribute('name', 'hide');
        } else {
            passwordInput.type = 'password';
            toggleBtn.setAttribute('name', 'show');
        }
    };

    // Add the cleanup check function if not already present
    function checkCleanup() {
        fetch('fetch_api.php?action=check_cleanup', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.warn('Cleanup check failed:', data.error);
            }
        })
        .catch(error => console.error('Cleanup check error:', error));
    }

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
