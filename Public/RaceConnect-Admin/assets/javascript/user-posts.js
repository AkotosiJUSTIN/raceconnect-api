document.addEventListener('DOMContentLoaded', () => {
    fetchPosts();

    function fetchPosts() {
        fetch('fetch_api.php?action=fetch_posts')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Fetched posts:', data);
                populatePosts(data.data);
            })
            .catch(error => {
                console.error('Error posts:', error);
            });
    }

    function populatePosts(posts) {
        const mainContent = document.getElementById('mainContent');
        if (!mainContent) {
            console.error('Error: #mainContent element not found.');
            return;
        }
    
        mainContent.innerHTML = '';
    
        if (!posts || posts.length === 0) {
            mainContent.innerHTML = `
                <div class="error-message">
                    <p>No reported posts found.</p>
                </div>
            `;
            return;
        }
    
        posts.forEach(post => {
            const postCard = document.createElement('div');
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('post_id');
            
            // Set data attribute for the post ID
            postCard.setAttribute('data-post-id', post.id);
            
            // Check if this post should be highlighted
            const shouldHighlight = highlightId === post.id.toString();
            
            // Add classes including highlight if needed
            postCard.className = `post-card ${post.status === 'Hidden' ? 'blurred' : ''} ${shouldHighlight ? 'highlighted' : ''}`;

            // If this is the highlighted post, scroll to it
            if (shouldHighlight) {
                setTimeout(() => {
                    postCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    console.log('Highlighting post:', post.id); // Debug log
                }, 100);
            }

            const imagesHtml = post.images && post.images.length > 0
                ? createCarousel(post.images, `post-${post.id}`)
                : '';

                postCard.innerHTML = `
                <div class="post-header">
                    <div class="user-info">
                        <span class="user-name">${post.title || 'Untitled'}</span>
                        <span class="post-time">${new Date(post.created_at).toLocaleString()}</span>
                    </div>
                    <div class="post-actions">
                        ${post.status === 'Hidden' 
                            ? `<button onclick="unhidePost('${post.id}')" class="unhide-btn" title="Unhide Post">
                                 <box-icon type='solid' name='show' color="white"></box-icon>
                               </button>`
                            : `<button onclick="hidePost('${post.id}')" class="hide-btn" title="Hide Post">
                                 <box-icon type='solid' name='hide' color="white"></box-icon>
                               </button>`
                        }
                        <button onclick="archivePost('${post.id}')" class="archive-btn" title="Archive Post">
                            <box-icon type='solid' name='archive-in' color="white"></box-icon>
                        </button>
                    </div>
                </div>
                <div class="report-info">
                    <span class="reporter-name">Reported by: ${post.reporter_username || 'Unknown'}</span>
                    <span class="report-reason">Reason: ${post.report_reason || 'No reason provided'}</span>
                    <span class="report-count">Reports: ${post.report_count || 0}</span>
                    <span class="report-date">on ${post.reported_at ? new Date(post.reported_at).toLocaleString() : 'Unknown date'}</span>
                </div>
                <div class="scrollable-content ${post.status === 'Hidden' ? 'blurred' : ''}">
                    <div class="product-details">
                        <div class="post-caption">
                            ${post.content || 'No content'}
                        </div>
                    </div>
                </div>
                ${imagesHtml}
            `;

            mainContent.appendChild(postCard);

            if (post.images && post.images.length > 0) {
                initCarousel(`post-${post.id}`);
            }
        });

        // Call removeHighlightAfterDelay after populating
        removeHighlightAfterDelay();
    }
    
    function removeHighlightAfterDelay() {
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('post_id');
        
        if (postId) {
            const highlightedElement = document.querySelector(`[data-post-id="${postId}"]`);
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

    // First, add this CSS to style the eye icon button
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

    // Make functions globally available
    window.hidePost = function(postId) {
        handlePostAction(postId, 'hide_post', 'Hide Post', 'Hidden!', 'The post has been hidden.');
    };

    window.unhidePost = function(postId) {
        handlePostAction(postId, 'unhide_post', 'Unhide Post', 'Unhidden!', 'The post has been unhidden.');
    };

    window.archivePost = function(postId) {
        Swal.fire({
            title: 'Archive Post',
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
                    
                    const response = await fetch('fetch_api.php?action=archive_post', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            post_id: postId,
                            password: password
                        })
                    });

                    const result = await response.json();
                    if (!result.success) {
                        Swal.showValidationMessage(result.error || 'Failed to archive post');
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
                    title: 'Archived!',
                    text: 'The post has been archived.',
                    icon: 'success',
                    timer: 1500
                }).then(() => {
                    fetchPosts();
                    if (Math.random() < 0.1) {
                        checkCleanup();
                    }
                });
            }
        });
    };
    
    // Add the toggle password function
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

    function handlePostAction(postId, action, confirmTitle, successTitle, successMessage) {
        Swal.fire({
            title: confirmTitle,
            text: `Are you sure you want to ${confirmTitle.toLowerCase()}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`fetch_api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ post_id: postId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: successTitle,
                            text: successMessage,
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            const postCard = document.querySelector(`[data-post-id="${postId}"]`);
                            if (postCard) {
                                if (action === 'archive_post') {
                                    postCard.style.display = 'none';
                                } else {
                                    postCard.classList.toggle('blurred');
                                    const scrollableContent = postCard.querySelector('.scrollable-content');
                                    const carousel = postCard.querySelector('.carousel');
                                    
                                    if (scrollableContent) {
                                        scrollableContent.classList.toggle('blurred');
                                    }
                                    if (carousel) {
                                        carousel.classList.toggle('blurred');
                                    }
                                    postCard.dataset.status = action === 'hide_post' ? 'Hidden' : 'Active';
                                }
                            }
                        });
                    } else {
                        throw new Error(result.error || `Failed to ${confirmTitle.toLowerCase()}`);
                    }
                })
                .catch(error => {
                    console.error('Action failed:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: error.message || 'An unexpected error occurred',
                        icon: 'error'
                    });
                });
            }
        });
    }
});