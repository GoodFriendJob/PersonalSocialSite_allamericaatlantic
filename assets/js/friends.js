// Java Documentdocument.addEventListener('DOMContentLoaded', () => {
    // Target the right sidebar container
    const sidebar = document.querySelector('.right-sidebar') || document.body;
    
    sidebar.addEventListener('click', async (e) => {
        // Intercept clicks on any "Add Friend" button
        if (e.target.classList.contains('add-friend-btn')) {
            const button = e.target;
            const friendId = button.getAttribute('data-user-id');
            
            button.disabled = true;
            button.textContent = 'Sending...';

            try {
                // Pointing to your routes folder relative to the root
                const response = await fetch('app/routes/add_friend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `friend_id=${friendId}`
                });

                const data = await response.json();

                if (data.success) {
                    button.textContent = 'Requested';
                    button.style.backgroundColor = '#6c757d'; // Turns gray on success
                } else {
                    alert(data.error || 'Request failed');
                    button.disabled = false;
                    button.textContent = 'Add Friend';
                }
            } catch (error) {
                console.error('Error:', error);
                button.disabled = false;
                button.textContent = 'Add Friend';
            }
        }
    });
});
