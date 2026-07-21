(function () {
  "use strict";

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
      throw new Error("The message server returned an invalid response.");
    }
    if (!response.ok || data.error || data.success === false) {
      throw new Error(data.error || data.message || "The message request failed.");
    }
    return data;
  }

  let els = {};
  const session = { threadId: null, peer: null, meId: Number(window.__CURRENT_USER_ID__) || 0, poll: null, lastId: 0 };

  // Unread tracking: baseline is set silently on first poll so we only toast
  // for messages that arrive while the page is open.
  const unread = { total: 0, byUser: {}, known: false, poll: null };
  let toastHost = null;

  document.addEventListener("DOMContentLoaded", () => {
    els = {
      modal: document.getElementById("chat-modal"),
      messages: document.getElementById("chat-messages"),
      form: document.getElementById("chat-form"),
      input: document.getElementById("chat-input"),
      status: document.getElementById("chat-status"),
      peerName: document.getElementById("chat-peer-name"),
      peerHandle: document.getElementById("chat-peer-handle"),
      peerAvatar: document.getElementById("chat-peer-avatar"),
    };
    if (!els.modal) return;

    els.modal.querySelectorAll("[data-chat-close]").forEach((node) =>
      node.addEventListener("click", close)
    );
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !els.modal.hidden) close();
    });
    els.form.addEventListener("submit", onSubmit);

    toastHost = document.createElement("div");
    toastHost.className = "chat-toast-host";
    document.body.appendChild(toastHost);

    startUnreadPolling();
  });

  // ---- Unread badge + toast -------------------------------------------------
  async function refreshUnread() {
    let data;
    try {
      data = await apiRequest("messages/unread");
    } catch (_) {
      return; // transient; next tick retries
    }

    const senders = Array.isArray(data.senders) ? data.senders : [];
    const nextByUser = {};
    senders.forEach((sender) => { nextByUser[sender.id] = sender.unread; });

    // Toast only for senders whose unread count grew since the last poll, and
    // never for the conversation that is currently open on screen.
    if (unread.known) {
      senders.forEach((sender) => {
        const previous = unread.byUser[sender.id] || 0;
        const openWithThem = !els.modal.hidden && session.peer && Number(session.peer.id) === Number(sender.id);
        if (sender.unread > previous && !openWithThem) {
          showToast(sender);
        }
      });
    }

    unread.byUser = nextByUser;
    unread.total = Number(data.total) || 0;
    unread.known = true;

    document.dispatchEvent(new CustomEvent("aaa:unread", {
      detail: { total: unread.total, byUser: unread.byUser, senders },
    }));
  }

  function showToast(sender) {
    if (!toastHost) return;
    const toast = document.createElement("button");
    toast.type = "button";
    toast.className = "chat-toast";

    const avatar = document.createElement("img");
    avatar.className = "chat-toast-avatar";
    avatar.src = sender.profile_pic || "assets/img/default-avatar.svg";
    avatar.alt = "";

    const body = document.createElement("span");
    body.className = "chat-toast-body";
    const name = document.createElement("strong");
    name.textContent = sender.display_name || sender.username || "New message";
    const text = document.createElement("small");
    text.textContent = sender.last_content || "sent you a message";
    body.append(name, text);

    toast.append(avatar, body);
    toast.addEventListener("click", () => {
      dismissToast(toast);
      open({
        id: sender.id,
        name: sender.display_name || sender.username || "Member",
        username: sender.username || "",
        avatar: sender.profile_pic || "assets/img/default-avatar.svg",
      });
    });

    toastHost.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("is-visible"));
    window.setTimeout(() => dismissToast(toast), 7000);
  }

  function dismissToast(toast) {
    if (!toast.isConnected) return;
    toast.classList.remove("is-visible");
    window.setTimeout(() => toast.remove(), 250);
  }

  function startUnreadPolling() {
    refreshUnread();
    if (unread.poll) window.clearInterval(unread.poll);
    unread.poll = window.setInterval(() => {
      if (!document.hidden) refreshUnread();
    }, 15000);
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) refreshUnread();
    });
  }

  async function open(peer) {
    if (!els.modal || !peer || !peer.id) return;
    session.peer = peer;
    session.threadId = null;
    session.lastId = 0;

    els.peerName.textContent = peer.name || "Member";
    els.peerHandle.textContent = peer.username ? `@${peer.username}` : "";
    els.peerAvatar.src = peer.avatar || "assets/img/default-avatar.svg";
    els.messages.innerHTML = '<p class="chat-empty">Loading conversation…</p>';
    els.input.value = "";
    els.status.textContent = "";

    els.modal.hidden = false;
    els.modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("chat-open");
    els.input.focus();

    try {
      const data = await apiRequest("threads", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: peer.id }),
      });
      session.threadId = Number(data.thread_id);
      await loadMessages();
      // Opening the thread marked its messages read server-side — sync badges.
      refreshUnread();
      startPolling();
    } catch (error) {
      els.messages.innerHTML = "";
      setStatus(error.message, true);
    }
  }

  function close() {
    if (!els.modal) return;
    stopPolling();
    els.modal.hidden = true;
    els.modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("chat-open");
    session.threadId = null;
    session.peer = null;
  }

  async function loadMessages() {
    if (!session.threadId) return;
    const data = await apiRequest(`threads/${session.threadId}/messages?limit=100`);
    if (typeof data.me === "number") session.meId = data.me;
    const messages = Array.isArray(data.messages) ? data.messages : [];
    renderMessages(messages);
  }

  function renderMessages(messages) {
    els.messages.replaceChildren();
    if (!messages.length) {
      const empty = document.createElement("p");
      empty.className = "chat-empty";
      empty.textContent = "Say hello 👋";
      els.messages.appendChild(empty);
      return;
    }
    messages.forEach((message) => els.messages.appendChild(bubble(message)));
    session.lastId = Number(messages[messages.length - 1].id) || session.lastId;
    scrollToEnd();
  }

  function bubble(message) {
    const mine = Number(message.sender_id) === Number(session.meId);
    const row = document.createElement("div");
    row.className = `chat-bubble ${mine ? "is-mine" : "is-theirs"}`;
    const text = document.createElement("p");
    text.className = "chat-bubble-text";
    text.textContent = message.content;
    const time = document.createElement("span");
    time.className = "chat-bubble-time";
    time.textContent = formatTime(message.created_at);
    row.append(text, time);
    return row;
  }

  async function onSubmit(event) {
    event.preventDefault();
    const content = els.input.value.trim();
    if (!content || !session.threadId) return;
    els.input.value = "";
    setStatus("");
    try {
      const data = await apiRequest(`threads/${session.threadId}/messages`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ content }),
      });
      els.messages.querySelector(".chat-empty")?.remove();
      if (data.message) {
        els.messages.appendChild(bubble(data.message));
        session.lastId = Number(data.message.id) || session.lastId;
        scrollToEnd();
      }
    } catch (error) {
      els.input.value = content;
      setStatus(error.message, true);
    }
  }

  // Light polling so a reply appears without a manual refresh.
  function startPolling() {
    stopPolling();
    session.poll = window.setInterval(async () => {
      if (els.modal.hidden || !session.threadId || document.hidden) return;
      try {
        await loadMessages();
      } catch (_) {
        /* transient; the next tick retries */
      }
    }, 5000);
  }

  function stopPolling() {
    if (session.poll) {
      window.clearInterval(session.poll);
      session.poll = null;
    }
  }

  function scrollToEnd() {
    els.messages.scrollTop = els.messages.scrollHeight;
  }

  function setStatus(message, isError) {
    els.status.textContent = message || "";
    els.status.classList.toggle("chat-status-error", Boolean(isError));
  }

  function formatTime(value) {
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "";
    return date.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  }

  window.AAAMessenger = {
    open,
    close,
    refreshUnread,
    unreadFor: (userId) => unread.byUser[userId] || 0,
    unreadTotal: () => unread.total,
  };
})();
