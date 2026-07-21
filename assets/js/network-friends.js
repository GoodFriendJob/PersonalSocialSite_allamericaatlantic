(function () {
  "use strict";

  const state = {
    activeView: "network",
    friends: [],
    network: [],
    loading: false,
  };

  document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("friend-search-input");
    const resultsDropdown = document.getElementById("search-results-dropdown");
    const viewButtons = document.querySelectorAll("[data-network-view]");

    viewButtons.forEach((button) => {
      button.addEventListener("click", () => {
        state.activeView = button.dataset.networkView === "friends" ? "friends" : "network";
        renderActiveList();
      });
    });

    if (searchInput && resultsDropdown) {
      searchInput.addEventListener("input", debounce(async (event) => {
        const query = event.target.value.trim();
        if (query.length < 2) {
          resultsDropdown.replaceChildren();
          resultsDropdown.style.display = "none";
          return;
        }
        await searchMembers(query, resultsDropdown);
      }, 300));
    }

    fetchFriendsNetwork();
    window.setInterval(() => {
      if (!document.hidden) fetchFriendsNetwork();
    }, 60000);
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) fetchFriendsNetwork();
    });
  });

  async function fetchFriendsNetwork() {
    if (state.loading) return;
    state.loading = true;
    try {
      const response = await fetch("app/routes/get_friends.php", {
        cache: "no-store",
        headers: { Accept: "application/json" },
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.error || `Network status update failed: ${response.status}`);
      }

      state.friends = Array.isArray(data.friends) ? data.friends : [];
      state.network = Array.isArray(data.network) ? data.network : [];
      renderActiveList();
    } catch (error) {
      console.error("Error fetching friends and network:", error);
      const list = document.getElementById("friendsList");
      if (list) {
        list.innerHTML = '<li class="loading-friends">Friends and network could not be loaded.</li>';
      }
    } finally {
      state.loading = false;
    }
  }

  function renderActiveList() {
    const list = document.getElementById("friendsList");
    const onlineCount = document.getElementById("onlineCount");
    const friendsTabCount = document.getElementById("friendsTabCount");
    const networkTabCount = document.getElementById("networkTabCount");
    const viewButtons = document.querySelectorAll("[data-network-view]");
    if (!list) return;

    if (friendsTabCount) friendsTabCount.textContent = String(state.friends.length);
    if (networkTabCount) networkTabCount.textContent = String(state.network.length);

    viewButtons.forEach((button) => {
      const active = button.dataset.networkView === state.activeView;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });

    const members = state.activeView === "friends" ? state.friends : state.network;
    const online = members.filter((member) => Boolean(member.is_online)).length;
    if (onlineCount) {
      const groupName = state.activeView === "friends" ? "friends" : "members";
      onlineCount.textContent = `${online} Online · ${members.length} ${groupName}`;
    }

    list.replaceChildren();
    if (!members.length) {
      const empty = document.createElement("li");
      empty.className = "loading-friends";
      empty.textContent = state.activeView === "friends"
        ? "No accepted friends yet. Search below to add one."
        : "No other network members are available yet.";
      list.appendChild(empty);
      return;
    }

    members.forEach((member) => list.appendChild(createMemberRow(member)));
  }

  function createMemberRow(member) {
    const item = document.createElement("li");
    item.className = `friend-item${member.is_online ? " is-online" : ""}`;

    const profileLink = document.createElement("a");
    profileLink.className = "friend-profile-link";
    profileLink.href = appUrl(`profile.php?u=${encodeURIComponent(member.username || "")}`);

    const avatarContainer = document.createElement("span");
    avatarContainer.className = "avatar-container";
    const img = document.createElement("img");
    img.className = "avatar";
    img.src = member.profile_pic || "assets/img/default-avatar.svg";
    img.alt = "";
    const indicator = document.createElement("span");
    indicator.className = `status-indicator ${member.is_online ? "online" : "offline"}`;
    avatarContainer.append(img, indicator);

    const info = document.createElement("span");
    info.className = "friend-info";
    const name = document.createElement("strong");
    name.textContent = member.display_name || member.username || "Member";
    const handle = document.createElement("small");
    handle.textContent = member.username ? `@${member.username}` : "Network member";
    const status = document.createElement("span");
    status.className = `friend-presence${member.is_online ? " is-online" : ""}`;
    status.textContent = member.is_online ? "Active now" : "Offline";
    info.append(name, handle, status);

    const badge = document.createElement("span");
    badge.className = "network-relation-badge";
    if (member.is_friend) {
      badge.textContent = "Friend";
      badge.classList.add("is-friend");
    } else if (member.is_connection) {
      badge.textContent = "Connection";
      badge.classList.add("is-connection");
    } else {
      badge.textContent = "Network";
    }

    profileLink.append(avatarContainer, info, badge);
    item.appendChild(profileLink);
    return item;
  }

  async function searchMembers(query, resultsDropdown) {
    try {
      const response = await fetch(`app/routes/search_users.php?q=${encodeURIComponent(query)}`);
      const data = await response.json();
      resultsDropdown.replaceChildren();
      resultsDropdown.style.display = "block";

      if (!data.success || !Array.isArray(data.users) || !data.users.length) {
        const empty = document.createElement("li");
        empty.className = "network-search-empty";
        empty.textContent = "No members found";
        resultsDropdown.appendChild(empty);
        return;
      }

      data.users.forEach((user) => {
        const item = document.createElement("li");
        item.className = "network-search-result";
        const label = document.createElement("span");
        label.textContent = `@${user.username}`;
        const button = document.createElement("button");
        button.className = "send-req-btn";
        button.dataset.id = String(user.id);
        button.textContent = user.friendship_status === "accepted"
          ? "Friends"
          : user.friendship_status ? "Pending" : "Add Friend";
        button.disabled = Boolean(user.friendship_status);
        item.append(label, button);
        resultsDropdown.appendChild(item);
      });
      attachRequestButtonListeners(resultsDropdown);
    } catch (error) {
      console.error("Error searching for network profiles:", error);
    }
  }

  function attachRequestButtonListeners(container) {
    container.querySelectorAll(".send-req-btn").forEach((button) => {
      button.addEventListener("click", async function () {
        const receiverId = this.dataset.id;
        this.disabled = true;
        this.textContent = "Sending...";
        try {
          const response = await fetch("app/routes/send_request.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ receiver_id: receiverId }),
          });
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(result.error || "Request failed");
          this.textContent = result.status === "accepted" ? "Friends" : "Pending";
          await fetchFriendsNetwork();
        } catch (error) {
          alert(error.message || "Failed sending request.");
          this.disabled = false;
          this.textContent = "Add Friend";
        }
      });
    });
  }

  function debounce(func, delay) {
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  }
})();
