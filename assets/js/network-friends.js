(function () {
  "use strict";

  // ---- API plumbing (same convention as posts.js) --------------------------
  function apiUrl(route) {
    const separator = route.indexOf("?");
    const path = separator === -1 ? route : route.slice(0, separator);
    const query = separator === -1 ? "" : `&${route.slice(separator + 1)}`;
    return appUrl(`app/index.php?route=${encodeURIComponent(path)}${query}`);
  }

  async function apiRequest(route, options) {
    const response = await fetch(apiUrl(route), {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
      ...(options || {}),
    });
    const raw = await response.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch (_) {
      throw new Error("The network server returned an invalid response.");
    }
    if (!response.ok || data.error || data.success === false) {
      throw new Error(data.error || data.message || "The network request failed.");
    }
    return data;
  }

  // ---- State ----------------------------------------------------------------
  const state = {
    activeView: "network",
    friends: [],
    members: [],
    requests: [],
    sent: [],
    loading: false,
  };

  const els = {};

  // Kept in sync from messages.js via the "aaa:unread" event so friend rows
  // can badge conversations that have unread messages.
  let unreadByUser = {};

  document.addEventListener("DOMContentLoaded", () => {
    els.list = document.getElementById("friendsList");
    els.onlineCount = document.getElementById("onlineCount");
    els.friendsTabCount = document.getElementById("friendsTabCount");
    els.networkTabCount = document.getElementById("networkTabCount");
    els.requestSection = document.getElementById("friend-requests");
    els.requestList = document.getElementById("requestList");
    els.requestCount = document.getElementById("requestCount");
    els.sentSection = document.getElementById("sent-requests");
    els.sentList = document.getElementById("sentList");
    els.sentCount = document.getElementById("sentCount");

    els.sidebarHeader = document.querySelector(".sidebar-right .sidebar-header");

    const searchInput = document.getElementById("friend-search-input");
    const resultsDropdown = document.getElementById("search-results-dropdown");

    // messages.js owns unread state; the sidebar just reflects it.
    document.addEventListener("aaa:unread", (event) => {
      unreadByUser = (event.detail && event.detail.byUser) || {};
      document.querySelectorAll("[data-peer-id]").forEach((button) => {
        applyUnreadBadge(button, Number(button.dataset.peerId));
      });
      updateHeaderBadge((event.detail && event.detail.total) || 0);
    });

    document.querySelectorAll("[data-network-view]").forEach((button) => {
      button.addEventListener("click", () => {
        state.activeView = button.dataset.networkView === "friends" ? "friends" : "network";
        render();
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
      document.addEventListener("click", (event) => {
        if (!resultsDropdown.contains(event.target) && event.target !== searchInput) {
          resultsDropdown.style.display = "none";
        }
      });
    }

    // The standalone "Add Friend" button just focuses the search field —
    // members are added straight from the search results below it.
    const addFriendButton = document.getElementById("add-friend-submit-btn");
    if (addFriendButton && searchInput) {
      addFriendButton.addEventListener("click", () => searchInput.focus());
    }

    fetchNetwork();
    window.setInterval(() => { if (!document.hidden) fetchNetwork(); }, 60000);
    document.addEventListener("visibilitychange", () => { if (!document.hidden) fetchNetwork(); });
  });

  // ---- Data -----------------------------------------------------------------
  async function fetchNetwork() {
    if (state.loading) return;
    state.loading = true;
    try {
      const data = await apiRequest("friends/network");
      state.friends = Array.isArray(data.friends) ? data.friends : [];
      state.members = Array.isArray(data.members) ? data.members : [];
      state.requests = Array.isArray(data.requests) ? data.requests : [];
      state.sent = Array.isArray(data.sent) ? data.sent : [];
      render();
    } catch (error) {
      console.error("Error fetching friends and network:", error);
      if (els.list) {
        els.list.innerHTML = '<li class="loading-friends">Friends and network could not be loaded.</li>';
      }
    } finally {
      state.loading = false;
    }
  }

  // ---- Rendering ------------------------------------------------------------
  function render() {
    if (!els.list) return;

    if (els.friendsTabCount) els.friendsTabCount.textContent = String(state.friends.length);
    if (els.networkTabCount) els.networkTabCount.textContent = String(state.members.length);

    document.querySelectorAll("[data-network-view]").forEach((button) => {
      const active = button.dataset.networkView === state.activeView;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });

    const members = state.activeView === "friends" ? state.friends : state.members;
    const online = members.filter((member) => Boolean(member.is_online)).length;
    if (els.onlineCount) {
      const groupName = state.activeView === "friends" ? "friends" : "members";
      els.onlineCount.textContent = `${online} Online · ${members.length} ${groupName}`;
    }

    els.list.replaceChildren();
    if (!members.length) {
      const empty = document.createElement("li");
      empty.className = "loading-friends";
      empty.textContent = state.activeView === "friends"
        ? "No accepted friends yet. Search below to add one."
        : "No other members are available yet.";
      els.list.appendChild(empty);
    } else {
      members.forEach((member) => {
        els.list.appendChild(
          state.activeView === "friends" ? friendRow(member) : memberRow(member)
        );
      });
    }

    renderRequests();
  }

  function renderRequests() {
    if (els.requestSection) {
      els.requestSection.hidden = state.requests.length === 0;
      if (els.requestCount) els.requestCount.textContent = String(state.requests.length);
      if (els.requestList) {
        els.requestList.replaceChildren();
        state.requests.forEach((member) => els.requestList.appendChild(incomingRow(member)));
      }
    }
    if (els.sentSection) {
      els.sentSection.hidden = state.sent.length === 0;
      if (els.sentCount) els.sentCount.textContent = String(state.sent.length);
      if (els.sentList) {
        els.sentList.replaceChildren();
        state.sent.forEach((member) => els.sentList.appendChild(sentRow(member)));
      }
    }
  }

  // Reusable identity block: avatar (with presence dot) + name/handle.
  function identity(member, small) {
    const link = document.createElement("a");
    link.className = "friend-profile-link";
    link.href = appUrl(`profile.php?u=${encodeURIComponent(member.username || "")}`);

    const avatarContainer = document.createElement("span");
    avatarContainer.className = "avatar-container";
    const img = document.createElement("img");
    img.className = "avatar";
    img.src = member.profile_pic || "assets/img/default-avatar.svg";
    img.alt = "";
    avatarContainer.appendChild(img);
    if (!small) {
      const indicator = document.createElement("span");
      indicator.className = `status-indicator ${member.is_online ? "online" : "offline"}`;
      avatarContainer.appendChild(indicator);
    }

    const info = document.createElement("span");
    info.className = "friend-info";
    const name = document.createElement("strong");
    name.textContent = member.display_name || member.username || "Member";
    const handle = document.createElement("small");
    handle.textContent = member.username ? `@${member.username}` : "Network member";
    info.append(name, handle);
    if (!small) {
      const status = document.createElement("span");
      status.className = `friend-presence${member.is_online ? " is-online" : ""}`;
      status.textContent = member.is_online ? "Active now" : "Offline";
      info.appendChild(status);
    }

    link.append(avatarContainer, info);
    return link;
  }

  // "Message" button that also carries an unread badge for this peer.
  function messageButton(member, row) {
    const button = actionBtn("Message", "is-primary");
    button.dataset.peerId = String(member.id);
    button.addEventListener("click", () => openMessenger(member));
    applyUnreadBadge(button, member.id, row);
    return button;
  }

  function applyUnreadBadge(button, userId, row) {
    const count = unreadByUser[userId] || 0;
    const host = row || button.closest(".friend-item");
    let badge = button.querySelector(".msg-unread-badge");
    if (count > 0) {
      if (!badge) {
        badge = document.createElement("span");
        badge.className = "msg-unread-badge";
        button.appendChild(badge);
      }
      badge.textContent = count > 9 ? "9+" : String(count);
      button.classList.add("has-unread");
      if (host) host.classList.add("has-unread");
    } else {
      if (badge) badge.remove();
      button.classList.remove("has-unread");
      if (host) host.classList.remove("has-unread");
    }
  }

  function updateHeaderBadge(total) {
    if (!els.sidebarHeader) return;
    let badge = document.getElementById("messagesUnreadBadge");
    if (total > 0) {
      if (!badge) {
        badge = document.createElement("span");
        badge.id = "messagesUnreadBadge";
        badge.className = "messages-unread-badge";
        badge.title = "Unread messages";
        els.sidebarHeader.appendChild(badge);
      }
      badge.textContent = total > 99 ? "99+" : `${total} new`;
    } else if (badge) {
      badge.remove();
    }
  }

  function actionBtn(label, variant) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `network-action-btn${variant ? ` ${variant}` : ""}`;
    button.textContent = label;
    return button;
  }

  // Accepted friend: message or remove.
  function friendRow(member) {
    const item = document.createElement("li");
    item.className = `friend-item${member.is_online ? " is-online" : ""}`;

    const actions = document.createElement("div");
    actions.className = "friend-actions";

    const message = messageButton(member, item);

    const remove = actionBtn("Remove", "is-muted");
    remove.addEventListener("click", async () => {
      if (!window.confirm(`Remove ${member.display_name || member.username} from your friends?`)) return;
      await act(remove, `friends/${member.id}`, { method: "DELETE" });
    });

    actions.append(message, remove);
    item.append(identity(member), actions);
    return item;
  }

  // Any member in the wider network: contextual relationship action.
  function memberRow(member) {
    const item = document.createElement("li");
    item.className = `friend-item${member.is_online ? " is-online" : ""}`;

    const actions = document.createElement("div");
    actions.className = "friend-actions";

    switch (member.friendship) {
      case "accepted": {
        actions.appendChild(messageButton(member, item));
        break;
      }
      case "pending_sent": {
        const cancel = actionBtn("Cancel", "is-muted");
        cancel.addEventListener("click", () => act(cancel, `friends/${member.id}`, { method: "DELETE" }));
        actions.appendChild(cancel);
        break;
      }
      case "pending_received": {
        const accept = actionBtn("Accept", "is-primary");
        accept.addEventListener("click", () => act(accept, `friends/${member.id}/accept`, { method: "POST" }));
        const decline = actionBtn("Decline", "is-muted");
        decline.addEventListener("click", () => act(decline, `friends/${member.id}/decline`, { method: "POST" }));
        actions.append(accept, decline);
        break;
      }
      case "blocked":
        break;
      default: {
        const add = actionBtn("Add", "is-primary");
        add.addEventListener("click", () => act(add, `friends/${member.id}/request`, { method: "POST" }));
        actions.appendChild(add);
      }
    }

    item.append(identity(member), actions);
    return item;
  }

  // Incoming request: accept or decline.
  function incomingRow(member) {
    const item = document.createElement("li");
    item.className = "request-item";

    const accept = actionBtn("Accept", "is-primary");
    accept.addEventListener("click", () => act(accept, `friends/${member.id}/accept`, { method: "POST" }));
    const decline = actionBtn("Decline", "is-muted");
    decline.addEventListener("click", () => act(decline, `friends/${member.id}/decline`, { method: "POST" }));

    const actions = document.createElement("div");
    actions.className = "friend-actions";
    actions.append(accept, decline);

    item.append(identity(member, true), actions);
    return item;
  }

  // Request I sent: cancel it.
  function sentRow(member) {
    const item = document.createElement("li");
    item.className = "request-item";

    const cancel = actionBtn("Cancel", "is-muted");
    cancel.addEventListener("click", () => act(cancel, `friends/${member.id}`, { method: "DELETE" }));

    const pending = document.createElement("span");
    pending.className = "request-pending-tag";
    pending.textContent = "Pending";

    const actions = document.createElement("div");
    actions.className = "friend-actions";
    actions.append(pending, cancel);

    item.append(identity(member, true), actions);
    return item;
  }

  // Run a relationship action, then refresh so every list stays consistent.
  async function act(button, route, options) {
    const siblings = button.parentElement ? [...button.parentElement.querySelectorAll("button")] : [button];
    siblings.forEach((b) => (b.disabled = true));
    const original = button.textContent;
    button.textContent = "…";
    try {
      await apiRequest(route, options);
      await fetchNetwork();
    } catch (error) {
      alert(error.message || "Action failed.");
      button.textContent = original;
      siblings.forEach((b) => (b.disabled = false));
    }
  }

  function openMessenger(member) {
    if (window.AAAMessenger && typeof window.AAAMessenger.open === "function") {
      window.AAAMessenger.open({
        id: member.id,
        name: member.display_name || member.username || "Member",
        username: member.username || "",
        avatar: member.profile_pic || "assets/img/default-avatar.svg",
      });
    } else {
      window.location.href = appUrl(`profile.php?u=${encodeURIComponent(member.username || "")}`);
    }
  }

  // ---- Search ---------------------------------------------------------------
  async function searchMembers(query, resultsDropdown) {
    try {
      const data = await apiRequest(`friends/search?q=${encodeURIComponent(query)}`);
      resultsDropdown.replaceChildren();
      resultsDropdown.style.display = "block";

      const results = Array.isArray(data.results) ? data.results : [];
      if (!results.length) {
        const empty = document.createElement("li");
        empty.className = "network-search-empty";
        empty.textContent = "No members found";
        resultsDropdown.appendChild(empty);
        return;
      }

      results.forEach((user) => {
        const item = document.createElement("li");
        item.className = "network-search-result";

        const label = document.createElement("span");
        const displayName = [user.first_name, user.last_name].filter(Boolean).join(" ");
        label.textContent = displayName ? `${displayName} · @${user.username}` : `@${user.username}`;

        const button = document.createElement("button");
        button.className = "send-req-btn";
        button.dataset.id = String(user.id);
        button.dataset.state = user.friendship || "none";
        applySearchButtonState(button, user.friendship);
        item.append(label, button);
        resultsDropdown.appendChild(item);
      });
    } catch (error) {
      console.error("Error searching for network profiles:", error);
    }
  }

  function applySearchButtonState(button, friendship) {
    switch (friendship) {
      case "accepted":
        button.textContent = "Friends";
        button.disabled = true;
        break;
      case "pending_sent":
        button.textContent = "Requested";
        button.disabled = true;
        break;
      case "pending_received":
        button.textContent = "Accept";
        button.disabled = false;
        break;
      default:
        button.textContent = "Add Friend";
        button.disabled = false;
    }
  }

  // Delegated: works for rows added after the listener was attached.
  document.addEventListener("click", async (event) => {
    const button = event.target.closest(".send-req-btn");
    if (!button || button.disabled) return;
    const id = button.dataset.id;
    const relationship = button.dataset.state;
    const route = relationship === "pending_received"
      ? `friends/${id}/accept`
      : `friends/${id}/request`;
    button.disabled = true;
    button.textContent = "Sending…";
    try {
      await apiRequest(route, { method: "POST" });
      button.textContent = relationship === "pending_received" ? "Friends" : "Requested";
      button.dataset.state = relationship === "pending_received" ? "accepted" : "pending_sent";
      await fetchNetwork();
    } catch (error) {
      alert(error.message || "Failed sending request.");
      applySearchButtonState(button, relationship);
    }
  });

  function debounce(func, delay) {
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  }
})();
