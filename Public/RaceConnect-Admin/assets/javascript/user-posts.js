document.addEventListener('DOMContentLoaded', () => {
    fetchPosts();

    function fetchPosts() {
        fetch('fetch_posts.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(posts => {
                console.log('Fetched posts:', posts); // Debugging statement
                populatePosts(posts);
            })
            .catch(error => console.error('Error fetching posts:', error));
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
                        <span class="user-name">${post.user_id}</span>
                        <span class="post-time">${new Date(post.created_at).toLocaleString()}</span>
                    </div>
                    <div class="post-actions">
                        <button class="edit-btn"><box-icon size="sm" name='edit' color="white"></box-icon></button>
                        <button class="unsee-btn"><box-icon size="sm" type='solid' name='low-vision' color="white"></box-icon></button>
                        <button class="delete-btn"><box-icon size="sm" type='solid' name='trash' color="white"></box-icon></button>
                    </div>
                </div>
                <!-- Scrollable Content -->
                <div class="scrollable-content">
                    <div class="post-caption">
                        ${post.content}
                    </div>
                    <div class="post-image">
                        <img src="${post.img_url}" alt="Post Image">
                    </div>
                </div>

                <!-- Non-Scrollable Interactions -->
                <div class="post-interactions">
                    <box-icon name='comment-detail'></box-icon><span class="comments">${post.comment_count}</span>
                </div>
            `;

            mainContent.appendChild(postCard);
        });
    }
});