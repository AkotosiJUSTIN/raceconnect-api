document.addEventListener('DOMContentLoaded', () => {
    fetchPosts();

    function fetchPosts() {
        fetch('fetch_api.php?action=fetch_posts')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    populatePosts(result.data);
                } else {
                    console.error('Server error:', result.error);
                }
            })
            .catch(error => {
                console.error('Error fetching posts:', error);
                // Log the response text for debugging
                fetch('fetch_api.php?action=fetch_posts')
                    .then(response => response.text())
                    .then(text => console.log('Response text:', text));
            });
    }

    function populatePosts(posts) {
        const mainContent = document.getElementById('mainContent');
        mainContent.innerHTML = ''; // Clear existing content

        posts.forEach(post => {
            const postCard = document.createElement('div');
            postCard.className = 'post-card';

            postCard.innerHTML = `
                <div class="post-header">
                    <div class="user-info">
                        <span class="user-name">${post.title}</span>
                        <span class="post-time">${new Date(post.created_at).toLocaleString()}</span>
                    </div>
                    <div class="post-actions">
                        <button class="unsee-btn" title="Hide Post"><box-icon type='solid' name='low-vision' color="white"></box-icon></button>
                        <button class="delete-btn" title="Archive Post"><box-icon size="sm" type='solid' name='archive-in' color="white"></box-icon></button>
                    </div>
                </div>
                <!-- Scrollable Content -->
                <div class="scrollable-content">
                    <div class="post-caption">
                        ${post.content}
                    </div>
                    <div class="post-image">
                        ${post.images.map(image => `<img src="${image}" alt="Post Image">`).join('')}
                    </div>
                </div>

                <!-- Non-Scrollable Interactions -->
                <div class="post-interactions">
                    <box-icon name='comment-detail'></box-icon><span class="comments">${post.comment_count}</span>
                </div>
            `;

            // Event listeners for the action buttons
            postCard.querySelector('.unsee-btn').addEventListener('click', () => hidePost(post.id, postCard));
            postCard.querySelector('.delete-btn').addEventListener('click', () => archivePost(post.id, postCard));

            mainContent.appendChild(postCard);
        });
    }

    function hidePost(postId, postCard) {
        fetch('fetch_api.php?action=hide_post', {
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
                    postCard.style.display = 'none'; // Hide the post card
                } else {
                    console.error('Error hiding post:', result.message);
                }
            })
            .catch(error => console.error('Error hiding post:', error));
    }

    function archivePost(postId, postCard) {
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
                    postCard.style.display = 'none'; // Hide the post card
                } else {
                    console.error('Error archiving post:', result.message);
                }
            })
            .catch(error => console.error('Error archiving post:', error));
    }
});