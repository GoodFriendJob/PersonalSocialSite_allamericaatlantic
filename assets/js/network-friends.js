// Java Document// js/network-sidebar.js

document.addEventListener("DOMContentLoaded", () => {
    // 1. Run your active friends list loader right away
    fetchFriendsNetwork(); 

    const searchInput = document.getElementById('friend-search-input');
    const resultsDropdown = document.getElementById('search-results-dropdown');

    // 2. Listen for dynamic live typing in the search input box
    searchInput.addEventListener('input', debounce(async function(e) {
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            resultsDropdown.innerHTML = '';
            resultsDropdown.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`app/routes/search_users.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            resultsDropdown.innerHTML = '';

            if (data.success && data.users && data.users.length > 0) {
                resultsDropdown.style.display = 'block';
                
                data.users.forEach(user => {
                    const li = document.createElement('li');
                    li.style.cssText = "display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-bottom:1px solid #eee;";
                    li.innerHTML = `
                        <span style="color:#333; font-weight:bold;">@${user.username}</span>
                        <button class="send-req-btn" data-id="${user.id}" style="background:#003366; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:12px;">Add</button>
                    `;
                    resultsDropdown.appendChild(li);
                });

                attachRequestButtonListeners();
            } else {
                resultsDropdown.style.display = 'block';
                resultsDropdown.innerHTML = '<li style="padding:8px 12px; color:#666; font-size:14px;">No members found</li>';
            }
        } catch (error) {
            console.error("Error searching for network profiles:", error);
        }
    }, 300));
});

// 3. Fetch active friends network from the database
async function fetchFriendsNetwork() {
    try {
        const response = await fetch('app/routes/get_friends.php');
        if (!response.ok) throw new Error("Network status update failed: " + response.status);
        const data = await response.json();
        const friendsList = document.getElementById('friendsList');
        let onlineCounter = 0;
        
        if (data.success && data.friends && data.friends.length > 0) {
            friendsList.innerHTML = '';
            data.friends.forEach(friend => {
                if (friend.is_online) onlineCounter++;
                const statusClass = friend.is_online ? 'online' : 'offline';
                const statusText = friend.is_online ? 'Active Now' : 'Offline';
                const initial = friend.username.charAt(0).toUpperCase();
                friendsList.innerHTML += `
                    <li class="friend-item">
                        <div class="avatar-container">
                            <div class="avatar">${initial}</div>
                            <div class="status-indicator ${statusClass}"></div>
                        </div>
                        <div class="friend-info">
                            <h4>${friend.username}</h4>
                            <p>${statusText}</p>
                        </div>
                    </li>
                `;
            });
            document.getElementById('onlineCount').innerText = `${onlineCounter} Online`;
        } else {
            friendsList.innerHTML = '<li class="loading-friends">No friends in your network yet.</li>';
        }
    } catch (error) {
        console.error("Error fetching friends network:", error);
        const friendsList = document.getElementById('friendsList');
        if (friendsList) friendsList.innerHTML = '<li class="loading-friends">Error loading network. Check console.</li>';
    }
}

// 4. Action function to send the friend request payload
function attachRequestButtonListeners() {
    document.querySelectorAll('.send-req-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const receiverId = this.getAttribute('data-id');
            this.disabled = true;
            this.textContent = 'Sending...';

            try {
                const response = await fetch('app/routes/send_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ receiver_id: receiverId })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    this.textContent = 'Sent';
                    this.style.background = '#6c757d';
                } else {
                    alert(result.error || 'Failed sending request.');
                    this.disabled = false;
                    this.textContent = 'Add';
                }
            } catch (error) {
                console.error("Request execution failure:", error);
                this.disabled = false;
                this.textContent = 'Add';
            }
        });
    });
}

// 5. Helper function to throttle database queries while typing
function debounce(func, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}
