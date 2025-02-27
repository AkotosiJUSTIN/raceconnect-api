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
                    <p>No reported posts found.</p>
                </div>
            `;
            return;
        }
    
        items.forEach(item => {
            const productCard = document.createElement('div');
            productCard.className = 'product-card';
            productCard.dataset.itemId = item.id;
            productCard.dataset.status = item.status;

            const contentClass = item.status === 'Hidden' ? 'blurred' : '';
            
            const imagesHtml = item.image_urls && item.image_urls.length > 0
                ? createCarousel(item.image_urls, `item-${item.id}`)
                : '';

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
                    </div>
                </div>
                ${imagesHtml}
            `;
    
            mainContent.appendChild(productCard);

            if (item.image_urls && item.image_urls.length > 0) {
                initCarousel(`item-${item.id}`);
            }
        });
    }

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
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemId}`
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
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemId}`
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
                                const scrollableContent = productCard.querySelector('.scrollable-content');
                                const carousel = productCard.querySelector('.carousel');
                                
                                if (scrollableContent) {
                                    scrollableContent.classList.remove('blurred');
                                }
                                if (carousel) {
                                    carousel.classList.remove('blurred');
                                }
                            }
                            fetchMarketplaceItems();
                        });
                    } else {
                        throw new Error(result.error || 'Failed to unhide item');
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

    window.archiveItem = function(itemId) {
        Swal.fire({
            title: 'Archive Item',
            text: 'Are you sure you want to archive this marketplace item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
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
                        Swal.fire({
                            title: 'Archived!',
                            text: 'The item has been archived.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            fetchMarketplaceItems();
                        });
                    } else {
                        throw new Error(result.error || 'Failed to archive item');
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
