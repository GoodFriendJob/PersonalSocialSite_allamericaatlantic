/**
 * Right sidebar: friend list, search, requests.
 *
 * The previous version called app/routes/search_users.php, get_friends.php and
 * send_request.php. The first two were fatal on load (require 'config.php',
 * which does not exist) and the third was never written. All three are now
 * replaced by FriendController routes.
 */
(function () {
  "use strict";

  const friendsList = document.getElementById("friendsList");
  const searchInput = document.getElementById("friend-search-input");
  const dropdown = document.getElementById("search-results-dropdown");
  const addBtn = document.getElementById("add-friend-submit-btn");
  const countEl = document.getElementById("onlineCount");
  const requestsList = document.getElementById("friend-requests");
  const sentList = document.getElementById("friend-sent");

  if (!friendsList) return;

  let friendCount = 0;
  let sentCount = 0;

  /** Header shows both totals: accepted friends and outgoing requests. */
  function updateHeaderCount() {
    if (!countEl) return;

    countEl.textContent =
      friendCount + (friendCount === 1 ? " friend" : " friends") +
      (sentCount ? " · " + sentCount + " requested" : "");
  }

  refreshAll();

  function refreshAll() {
    loadFriends();
    loadSent();
    loadRequests();
  }

  /* ===================== FRIEND LIST ===================== */
  async function loadFriends() {
    try {
      const data = await Api.get("friends");
      renderFriends(data.friends || []);
    } catch (err) {
      friendsList.innerHTML = '<li class="loading-friends">Could not load your network.</li>';
    }
  }

  function renderFriends(friends) {
    friendCount = friends.length;
    updateHeaderCount();

    if (!friends.length) {
      friendsList.innerHTML = '<li class="loading-friends">No friends yet — search for someone below.</li>';
      return;
    }

    friendsList.innerHTML = friends
      .map(function (f) {
        const name = [f.first_name, f.last_name].filter(Boolean).join(" ") || f.username;
        const avatar = f.profile_pic
          ? '<img class="friend-avatar" src="' + Api.escape(Api.assetUrl(f.profile_pic)) + '" alt="">'
          : '<div class="avatar-small">' + Api.escape(f.username.slice(0, 2).toUpperCase()) + "</div>";

        return (
          '<li class="friend-row" data-user-id="' + f.id + '">' + avatar +
            '<div class="friend-info">' +
              '<p class="friend-name">' + Api.escape(name) + "</p>" +
              '<p class="friend-role">@' + Api.escape(f.username) +
                (f.sport ? " · " + Api.escape(f.sport) : "") + "</p>" +
            "</div>" +
            '<button class="friend-message-btn" data-message="' + f.id + '" type="button">Message</button>' +
          "</li>"
        );
      })
      .join("");

    friendsList.querySelectorAll("[data-message]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        messageUser(btn.dataset.message);
      });
    });
  }

  /* ============= OUTGOING REQUESTS (I asked them) ========= */
  async function loadSent() {
    if (!sentList) return;

    try {
      const data = await Api.get("friends/sent");
      const requests = data.requests || [];

      sentCount = requests.length;
      updateHeaderCount();

      if (!requests.length) {
        sentList.innerHTML = "";
        return;
      }

      sentList.innerHTML =
        '<h4 class="requests-title">Friend requested <span class="group-count">' +
          requests.length + "</span></h4>" +
        requests
          .map(function (r) {
            const name = [r.first_name, r.last_name].filter(Boolean).join(" ") || r.username;
            const avatar = r.profile_pic
              ? '<img class="friend-avatar" src="' + Api.escape(Api.assetUrl(r.profile_pic)) + '" alt="">'
              : '<div class="avatar-small">' + Api.escape(r.username.slice(0, 2).toUpperCase()) + "</div>";

            return (
              '<div class="request-row" data-sent="' + r.id + '">' + avatar +
                '<div class="friend-info">' +
                  '<p class="friend-name">' + Api.escape(name) + "</p>" +
                  '<p class="friend-role">@' + Api.escape(r.username) + " · pending</p>" +
                "</div>" +
                '<button class="request-cancel" data-cancel="' + r.id + '" type="button">Cancel request</button>' +
              "</div>"
            );
          })
          .join("");

      sentList.querySelectorAll("[data-cancel]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          cancelRequest(btn.dataset.cancel, btn);
        });
      });
    } catch (err) {
      sentList.innerHTML = "";
    }
  }

  async function cancelRequest(userId, btn) {
    btn.disabled = true;
    btn.textContent = "Cancelling…";

    try {
      await Api.del("friends/" + userId);
      refreshAll();
    } catch (err) {
      alert("Could not cancel the request: " + err.message);
      btn.disabled = false;
      btn.textContent = "Cancel request";
    }
  }

  /* =================== INCOMING REQUESTS ================== */
  async function loadRequests() {
    if (!requestsList) return;

    try {
      const data = await Api.get("friends/pending");
      const requests = data.requests || [];

      if (!requests.length) {
        requestsList.innerHTML = "";
        return;
      }

      requestsList.innerHTML =
        '<h4 class="requests-title">Requests received <span class="group-count">' +
          requests.length + "</span></h4>" +
        requests
          .map(function (r) {
            const name = [r.first_name, r.last_name].filter(Boolean).join(" ") || r.username;
            return (
              '<div class="request-row" data-request="' + r.id + '">' +
                '<span class="request-name">' + Api.escape(name) + "</span>" +
                '<button class="request-accept" data-accept="' + r.id + '" type="button">Accept</button>' +
                '<button class="request-decline" data-decline="' + r.id + '" type="button">Decline</button>' +
              "</div>"
            );
          })
          .join("");

      requestsList.querySelectorAll("[data-accept]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          respond(btn.dataset.accept, "accept");
        });
      });
      requestsList.querySelectorAll("[data-decline]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          respond(btn.dataset.decline, "decline");
        });
      });
    } catch (err) {
      requestsList.innerHTML = "";
    }
  }

  async function respond(userId, action) {
    try {
      await Api.post("friends/" + userId + "/" + action);
      refreshAll();
    } catch (err) {
      alert("Could not " + action + " the request: " + err.message);
    }
  }

  /* ======================== SEARCH ======================== */
  if (searchInput) {
    searchInput.addEventListener("input", debounce(runSearch, 300));
  }

  if (addBtn) {
    addBtn.addEventListener("click", function () {
      if (!searchInput.value.trim()) {
        searchInput.focus();
        return;
      }
      runSearch();
    });
  }

  async function runSearch() {
    const q = searchInput.value.trim();

    if (q.length < 2) {
      hideDropdown();
      return;
    }

    try {
      const data = await Api.get("friends/search", { q: q });
      renderSearch(data.results || []);
    } catch (err) {
      dropdown.innerHTML = '<li class="search-empty">Search failed.</li>';
      dropdown.style.display = "block";
    }
  }

  function renderSearch(results) {
    if (!results.length) {
      dropdown.innerHTML = '<li class="search-empty">No users found.</li>';
      dropdown.style.display = "block";
      return;
    }

    dropdown.innerHTML = results
      .map(function (u) {
        const name = [u.first_name, u.last_name].filter(Boolean).join(" ") || u.username;
        return (
          '<li class="search-row">' +
            '<span class="search-name">' + Api.escape(name) +
              ' <span class="search-handle">@' + Api.escape(u.username) + "</span></span>" +
            friendshipButton(u) +
          "</li>"
        );
      })
      .join("");

    dropdown.style.display = "block";

    dropdown.querySelectorAll("[data-add]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        sendRequest(btn.dataset.add, btn);
      });
    });
  }

  function friendshipButton(user) {
    switch (user.friendship) {
      case "accepted":
        return '<span class="search-status">Friends</span>';
      case "pending_sent":
        return '<span class="search-status">Requested</span>';
      case "pending_received":
        return '<button class="search-add" data-add="' + user.id + '" type="button">Accept</button>';
      case "blocked":
        return '<span class="search-status">Blocked</span>';
      default:
        return '<button class="search-add" data-add="' + user.id + '" type="button">Add</button>';
    }
  }

  async function sendRequest(userId, btn) {
    btn.disabled = true;
    try {
      const res = await Api.post("friends/" + userId + "/request");
      btn.outerHTML =
        '<span class="search-status">' + (res.friendship === "accepted" ? "Friends" : "Requested") + "</span>";

      // refreshAll so the new request shows up under "Friend requested" too.
      refreshAll();
    } catch (err) {
      alert(err.message);
      btn.disabled = false;
    }
  }

  function hideDropdown() {
    if (!dropdown) return;
    dropdown.innerHTML = "";
    dropdown.style.display = "none";
  }

  document.addEventListener("click", function (e) {
    if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput && e.target !== addBtn) {
      hideDropdown();
    }
  });

  /* ======================== HELPERS ======================= */
  async function messageUser(userId) {
    const body = prompt("Send a message:");
    if (!body) return;

    try {
      const thread = await Api.post("threads", { user_id: Number(userId) });
      await Api.post("threads/" + thread.thread_id + "/messages", { content: body });
      alert("Message sent.");
    } catch (err) {
      alert("Could not send message: " + err.message);
    }
  }

  function debounce(fn, delay) {
    let timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(fn, delay);
    };
  }
})();
