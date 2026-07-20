/**
 * Network Friends sidebar: online friends, incoming requests, sent requests.
 *
 * Previously this talked to app/routes/get_friends.php, search_users.php and
 * send_request.php — standalone scripts with no accept, no decline and no
 * cancel, so a request could be sent but never answered. It now uses the
 * router-backed FriendController, which owns the whole lifecycle and writes
 * the notification rows the rest of the app expects.
 */
(function () {
  "use strict";

  const friendsList = document.getElementById("friendsList");
  const onlineCount = document.getElementById("onlineCount");
  const requestsBlock = document.getElementById("friend-requests-block");
  const requestsList = document.getElementById("friend-requests-list");
  const requestsCount = document.getElementById("friend-requests-count");
  const sentBlock = document.getElementById("friend-sent-block");
  const sentList = document.getElementById("friend-sent-list");
  const sentCount = document.getElementById("friend-sent-count");
  const statusLine = document.getElementById("friend-network-status");
  const searchInput = document.getElementById("friend-search-input");
  const searchDropdown = document.getElementById("search-results-dropdown");
  const addFriendBtn = document.getElementById("add-friend-submit-btn");

  if (!friendsList) return;

  /** Presence counts someone online for 5 minutes; refresh a little inside that. */
  const REFRESH_MS = 60000;

  /* ------------------------------------------------------------------ */
  /* Transport                                                           */
  /* ------------------------------------------------------------------ */

  function apiUrl(route) {
    const separator = route.indexOf("?");
    const path = separator === -1 ? route : route.slice(0, separator);
    const query = separator === -1 ? "" : `&${route.slice(separator + 1)}`;
    const relative = `app/index.php?route=${encodeURIComponent(path)}${query}`;
    // appUrl is defined by main.js, which loads first; guard anyway so the
    // sidebar degrades to a relative URL rather than throwing.
    return typeof appUrl === "function" ? appUrl(relative) : relative;
  }

  async function apiRequest(route, options) {
    const response = await fetch(apiUrl(route), {
      credentials: "same-origin",
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

  function showStatus(message, isError) {
    if (!statusLine) return;
    statusLine.textContent = message || "";
    statusLine.classList.toggle("is-error", Boolean(isError));
    if (message) {
      window.setTimeout(() => {
        if (statusLine.textContent === message) statusLine.textContent = "";
      }, 4000);
    }
  }

  /* ------------------------------------------------------------------ */
  /* Rendering                                                           */
  /* ------------------------------------------------------------------ */

  function avatarFor(person, extraClass) {
    const wrapper = document.createElement("div");
    wrapper.className = "avatar-container";

    const img = document.createElement("img");
    img.className = extraClass || "avatar";
    img.src = person.profile_pic || "assets/img/default-avatar.svg";
    img.alt = "";
    img.addEventListener("error", () => {
      img.src = "assets/img/default-avatar.svg";
    }, { once: true });
    wrapper.appendChild(img);

    return wrapper;
  }

  function renderFriends(friends) {
    friendsList.replaceChildren();

    if (!friends.length) {
      const empty = document.createElement("li");
      empty.className = "loading-friends";
      empty.textContent = "No friends in your network yet.";
      friendsList.appendChild(empty);
      return;
    }

    friends.forEach((friend) => {
      const item = document.createElement("li");
      item.className = "friend-item";

      const avatar = avatarFor(friend);
      const indicator = document.createElement("div");
      indicator.className = `status-indicator ${friend.is_online ? "online" : "offline"}`;
      avatar.appendChild(indicator);

      const info = document.createElement("div");
      info.className = "friend-info";
      const name = document.createElement("h4");
      name.textContent = friend.display_name || friend.username;
      const status = document.createElement("p");
      status.textContent = friend.is_online ? "Active Now" : "Offline";
      info.append(name, status);

      item.append(avatar, info);
      friendsList.appendChild(item);
    });
  }

  /**
   * One row builder for both request lists. `actions` is a list of
   * [label, className, handler] — incoming gets Accept/Decline, outgoing
   * gets Cancel.
   */
  function renderRequestRows(list, block, counter, people, actions) {
    if (!list || !block) return;

    list.replaceChildren();
    block.hidden = people.length === 0;
    if (counter) counter.textContent = String(people.length);

    people.forEach((person) => {
      const row = document.createElement("li");
      row.className = "request-row";

      const info = document.createElement("div");
      info.className = "request-info";
      const name = document.createElement("p");
      name.className = "request-name";
      name.textContent = person.display_name || person.username;
      const handle = document.createElement("p");
      handle.className = "request-handle";
      handle.textContent = `@${person.username}`;
      info.append(name, handle);

      const buttons = document.createElement("div");
      buttons.className = "request-actions";

      actions.forEach(([label, className, handler]) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = className;
        button.textContent = label;
        button.addEventListener("click", async () => {
          // Disable the whole row: accept and decline race on the same row.
          const rowButtons = buttons.querySelectorAll("button");
          rowButtons.forEach((b) => (b.disabled = true));
          try {
            await handler(person);
            await loadNetwork();
          } catch (error) {
            showStatus(error.message, true);
            rowButtons.forEach((b) => (b.disabled = false));
          }
        });
        buttons.appendChild(button);
      });

      row.append(avatarFor(person, "avatar avatar-small"), info, buttons);
      list.appendChild(row);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Actions                                                             */
  /* ------------------------------------------------------------------ */

  const acceptRequest = (person) =>
    apiRequest(`friends/${person.id}/accept`, { method: "POST" });

  const declineRequest = (person) =>
    apiRequest(`friends/${person.id}/decline`, { method: "POST" });

  // Cancelling a request I sent and unfriending are the same delete.
  const cancelRequest = (person) =>
    apiRequest(`friends/${person.id}`, { method: "DELETE" });

  const sendRequest = (userId) =>
    apiRequest(`friends/${userId}/request`, { method: "POST" });

  /* ------------------------------------------------------------------ */
  /* Loading                                                             */
  /* ------------------------------------------------------------------ */

  async function loadNetwork() {
    try {
      const data = await apiRequest("friends/network");

      renderFriends(data.friends || []);
      if (onlineCount) onlineCount.textContent = `${data.online_count || 0} Online`;

      renderRequestRows(requestsList, requestsBlock, requestsCount, data.requests || [], [
        ["Accept", "request-accept", acceptRequest],
        ["Decline", "request-decline", declineRequest],
      ]);

      renderRequestRows(sentList, sentBlock, sentCount, data.sent || [], [
        ["Cancel", "request-cancel", cancelRequest],
      ]);
    } catch (error) {
      console.error("Error fetching friends network:", error);
      friendsList.replaceChildren();
      const failed = document.createElement("li");
      failed.className = "loading-friends";
      failed.textContent = "Could not load your network.";
      friendsList.appendChild(failed);
    }
  }

  /* ------------------------------------------------------------------ */
  /* Search and add                                                      */
  /* ------------------------------------------------------------------ */

  const RELATIONSHIP_LABELS = {
    accepted: "Friends",
    pending_sent: "Requested",
    pending_received: "Accept",
    blocked: "Unavailable",
  };

  function renderSearchResults(results) {
    if (!searchDropdown) return;

    searchDropdown.replaceChildren();
    searchDropdown.style.display = "block";

    if (!results.length) {
      const empty = document.createElement("li");
      empty.className = "search-empty";
      empty.textContent = "No members found";
      searchDropdown.appendChild(empty);
      return;
    }

    results.forEach((user) => {
      const row = document.createElement("li");
      row.className = "search-row";

      const label = document.createElement("span");
      label.className = "search-name";
      const fullName = [user.first_name, user.last_name].filter(Boolean).join(" ");
      label.textContent = fullName ? `${fullName} (@${user.username})` : `@${user.username}`;

      const button = document.createElement("button");
      button.type = "button";
      button.className = "search-add";
      button.textContent = RELATIONSHIP_LABELS[user.friendship] || "Add";
      // Already friends or blocked is terminal; a request I already sent is
      // not actionable here — cancel it from the Requests sent list instead.
      button.disabled = user.friendship === "accepted"
        || user.friendship === "blocked"
        || user.friendship === "pending_sent";

      button.addEventListener("click", async () => {
        button.disabled = true;
        const original = button.textContent;
        button.textContent = "...";
        try {
          // A request from them that I answer with Add is an accept, which is
          // exactly what the request endpoint does with a mirrored pending row.
          await sendRequest(user.id);
          button.textContent = user.friendship === "pending_received" ? "Friends" : "Requested";
          showStatus(
            user.friendship === "pending_received"
              ? `You and @${user.username} are now friends.`
              : `Friend request sent to @${user.username}.`
          );
          await loadNetwork();
        } catch (error) {
          button.textContent = original;
          button.disabled = false;
          showStatus(error.message, true);
        }
      });

      row.append(label, button);
      searchDropdown.appendChild(row);
    });
  }

  async function runSearch(query) {
    if (!searchDropdown) return;

    if (query.length < 2) {
      searchDropdown.replaceChildren();
      searchDropdown.style.display = "none";
      return;
    }

    try {
      const data = await apiRequest(`friends/search?q=${encodeURIComponent(query)}`);
      renderSearchResults(data.results || []);
    } catch (error) {
      console.error("Error searching for network profiles:", error);
      showStatus(error.message, true);
    }
  }

  function debounce(fn, delay) {
    let timeout;
    return function (...args) {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => fn.apply(this, args), delay);
    };
  }

  if (searchInput) {
    searchInput.addEventListener("input", debounce((event) => {
      runSearch(event.target.value.trim());
    }, 300));
  }

  // The Add Friend button has no target of its own — it re-runs the search so
  // the user picks a specific member, which is the only unambiguous action.
  if (addFriendBtn) {
    addFriendBtn.addEventListener("click", () => {
      const query = searchInput ? searchInput.value.trim() : "";
      if (query.length < 2) {
        showStatus("Type at least 2 characters to find a member.", true);
        if (searchInput) searchInput.focus();
        return;
      }
      runSearch(query);
    });
  }

  document.addEventListener("click", (event) => {
    if (!searchDropdown || searchDropdown.style.display === "none") return;
    if (searchDropdown.contains(event.target) || event.target === searchInput) return;
    searchDropdown.style.display = "none";
  });

  /* ------------------------------------------------------------------ */

  loadNetwork();

  // Presence goes stale on its own, and requests arrive while the tab sits
  // open. Only poll a visible tab — a backgrounded one has nothing to show.
  window.setInterval(() => {
    if (document.visibilityState === "visible") loadNetwork();
  }, REFRESH_MS);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") loadNetwork();
  });
})();
