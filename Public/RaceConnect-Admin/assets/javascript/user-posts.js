document.addEventListener('DOMContentLoaded', () => {
    function fetchPosts() {
        fetch('fetch_api.php?action=fetch_posts', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin' // Important for session cookies
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new TypeError("Expected JSON response");
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                if (Array.isArray(result.data)) {
                    populatePosts(result.data);
                } else {
                    throw new Error('Invalid data format received');
                }
            } else {
                throw new Error(result.error || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error fetching posts:', error);
            const mainContent = document.getElementById('mainContent');
            mainContent.innerHTML = `
                <div class="error-message">
                    <p>Failed to load posts. Please try again later.</p>
                    <p class="error-details">${error.message}</p>
                    <button onclick="fetchPosts()" class="retry-btn">Retry</button>
                </div>
            `;
        });
    }
    
    fetchPosts();

    function populatePosts(posts) {
        const mainContent = document.getElementById('mainContent');
        mainContent.innerHTML = '';
    
        if (posts.length === 0) {
            mainContent.innerHTML = `
                <div class="error-message">
                    <p>No reported posts found.</p>
                </div>
            `;
            return;
        }
    
        posts.forEach(post => {
            const postCard = document.createElement('div');
            postCard.className = `post-card ${post.status === 'Hidden' ? 'blurred' : ''}`;
            postCard.dataset.postId = post.id;
            postCard.dataset.status = post.status;
    
            const imagesHtml = Array.isArray(post.images) && post.images.length > 0
                ? post.images.map(image => `<img src="${image}" alt="Post Image">`).join('')
                : '';
    
            postCard.innerHTML = `
                <div class="post-header">
                    <div class="user-info">
                        <span class="user-name">${post.title || 'Untitled'}</span>
                        <span class="post-time">${new Date(post.created_at).toLocaleString()}</span>
                    </div>
                    <div class="post-actions">
                        ${post.status === 'Hidden' 
                            ? `<button class="unhide-btn" title="Unhide Post">
                                 <box-icon type='solid' name='show' color="white"></box-icon>
                               </button>`
                            : `<button class="hide-btn" title="Hide Post">
                                 <box-icon type='solid' name='hide' color="white"></box-icon>
                               </button>`
                        }
                        <button class="archive-btn" title="Archive Post">
                            <box-icon type='solid' name='archive-in' color="white"></box-icon>
                        </button>
                    </div>
                </div>
                <div class="report-info">
                    <span class="report-reason">Reported: ${post.report_reason || 'No reason provided'}</span>
                    <span class="report-date">on ${post.reported_at ? new Date(post.reported_at).toLocaleString() : 'Unknown date'}</span>
                </div>
                <div class="scrollable-content ${post.status === 'Hidden' ? 'blurred' : ''}">
                    <div class="post-caption">
                        ${post.content || 'No content'}
                    </div>
                    ${imagesHtml ? `<div class="post-image">${imagesHtml}</div>` : ''}
                </div>
            `;
    
            // Add event listeners
            const hideBtn = postCard.querySelector('.hide-btn');
            const unhideBtn = postCard.querySelector('.unhide-btn');
            const archiveBtn = postCard.querySelector('.archive-btn');
    
            if (hideBtn) {
                hideBtn.addEventListener('click', () => hidePost(post.id, postCard));
            }
            if (unhideBtn) {
                unhideBtn.addEventListener('click', () => unhidePost(post.id, postCard));
            }
            if (archiveBtn) {
                archiveBtn.addEventListener('click', () => archivePost(post.id, postCard));
            }
    
            mainContent.appendChild(postCard);
        });
    }
    
    function hidePost(postId, postCard) {
        Swal.fire({
            title: 'Hide Post',
            text: 'Are you sure you want to hide this post?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('fetch_api.php?action=hide_post', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${encodeURIComponent(postId)}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: 'Hidden!',
                            text: 'The post has been hidden.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            postCard.classList.add('blurred');
                            postCard.dataset.status = 'Hidden';
                            updatePostActions(postCard);
                        });
                    } else {
                        throw new Error(result.error || 'Failed to hide post');
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
    }
    
    function unhidePost(postId, postCard) {
        Swal.fire({
            title: 'Unhide Post',
            text: 'Are you sure you want to unhide this post?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('fetch_api.php?action=unhide_post', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${encodeURIComponent(postId)}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: 'Unhidden!',
                            text: 'The post has been unhidden.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            postCard.classList.remove('blurred');
                            postCard.dataset.status = 'Active';
                            const scrollableContent = postCard.querySelector('.scrollable-content');
                            if (scrollableContent) {
                                scrollableContent.classList.remove('blurred');
                            }
                            updatePostActions(postCard);
                        });
                    } else {
                        throw new Error(result.error || 'Failed to unhide post');
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
    }
    
    function updatePostActions(postCard) {
        const actionsDiv = postCard.querySelector('.post-actions');
        const isHidden = postCard.dataset.status === 'Hidden';
        
        actionsDiv.innerHTML = `
            ${isHidden 
                ? `<button class="unhide-btn" title="Unhide Post">
                     <box-icon type='solid' name='show' color="white"></box-icon>
                   </button>`
                : `<button class="hide-btn" title="Hide Post">
                     <box-icon type='solid' name='hide' color="white"></box-icon>
                   </button>`
            }
            <button class="archive-btn" title="Archive Post">
                <box-icon type='solid' name='archive-in' color="white"></box-icon>
            </button>
        `;
    
        // Reattach event listeners
        if (isHidden) {
            const unhideBtn = actionsDiv.querySelector('.unhide-btn');
            if (unhideBtn) {
                unhideBtn.addEventListener('click', () => unhidePost(postCard.dataset.postId, postCard));
            }
        } else {
            const hideBtn = actionsDiv.querySelector('.hide-btn');
            if (hideBtn) {
                hideBtn.addEventListener('click', () => hidePost(postCard.dataset.postId, postCard));
            }
        }
    
        const archiveBtn = actionsDiv.querySelector('.archive-btn');
        if (archiveBtn) {
            archiveBtn.addEventListener('click', () => archivePost(postCard.dataset.postId, postCard));
        }
    }

    function archivePost(postId, postCard) {
        Swal.fire({
            title: 'Archive Post',
            text: 'Are you sure you want to archive this post?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('fetch_api.php?action=archive_post', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${encodeURIComponent(postId)}`
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
                            title: 'Archived!',
                            text: 'The post has been archived.',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            postCard.style.display = 'none';
                        });
                    } else {
                        throw new Error(result.error || 'Failed to archive post');
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
    }
});