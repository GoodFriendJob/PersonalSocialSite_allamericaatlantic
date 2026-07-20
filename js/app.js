// =========================================================
// DRAWER LOGIC (Profile + Friends)
// =========================================================

const profileDrawer = document.getElementById("profile-drawer");
const friendsDrawer = document.getElementById("friends-drawer");

const btnProfile = document.getElementById("btn-profile");
const btnFriends = document.getElementById("btn-friends");

const drawerCloseButtons = document.querySelectorAll(".drawer-close");

// Open profile drawer
btnProfile.addEventListener("click", () => {
  profileDrawer.classList.add("open");
  friendsDrawer.classList.remove("open");
});

// Open friends drawer
btnFriends.addEventListener("click", () => {
  friendsDrawer.classList.add("open");
  profileDrawer.classList.remove("open");
});

// Close drawer buttons
drawerCloseButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    const target = btn.getAttribute("data-close");
    document.getElementById(target).classList.remove("open");
  });
});

// Close drawers when clicking outside
document.addEventListener("click", (e) => {
  const isDrawer = e.target.closest(".drawer");
  const isButton = e.target.closest(".icon-btn");

  if (!isDrawer && !isButton) {
    profileDrawer.classList.remove("open");
    friendsDrawer.classList.remove("open");
  }
});

// =========================================================
// COMPOSER LOGIC
// =========================================================

const composerInput = document.querySelector(".composer-input");

if (composerInput) {
  composerInput.addEventListener("input", () => {
    composerInput.style.height = "auto";
    composerInput.style.height = composerInput.scrollHeight + "px";
  });
}

// =========================================================
// POST BUTTON (placeholder)
// =========================================================

const postButton = document.querySelector(".composer .btn-primary");

if (postButton) {
  postButton.addEventListener("click", () => {
    alert("Posting feature will be connected to your backend.");
  });
}

// =========================================================
// SWIPE GESTURES (Mobile)
// =========================================================

let touchStartX = 0;

document.addEventListener("touchstart", (e) => {
  touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener("touchend", (e) => {
  const touchEndX = e.changedTouches[0].screenX;
  const diff = touchEndX - touchStartX;

  if (diff > 80) {
    profileDrawer.classList.add("open");
    friendsDrawer.classList.remove("open");
  }

  if (diff < -80) {
    friendsDrawer.classList.add("open");
    profileDrawer.classList.remove("open");
  }
});

// =========================================================
// LIVE NOTIFICATIONS
// =========================================================

const btnNotifications = document.getElementById("btn-notifications");
const notifPanel = document.getElementById("notifications-panel");
const notifList = document.getElementById("notifications-list");
const notifDot = document.getElementById("notif-dot");

btnNotifications.addEventListener("click", async () => {
  notifPanel.classList.toggle("open");

  await fetch("/api/notifications_mark_read.php");
  notifDot.style.display = "none";
});

async function loadNotifications() {
  const res = await fetch("/api/notifications_fetch.php");
  const items = await res.json();

  notifList.innerHTML = "";
  let unread = false;

  items.forEach(n => {
    const div = document.createElement("div");
    div.className = "notification-item" + (n.is_read ? "" : " unread");
    div.textContent = n.message;

    if (!n.is_read) unread = true;

    notifList.appendChild(div);
  });

  notifDot.style.display = unread ? "block" : "none";
}

document.addEventListener("DOMContentLoaded", () => {
  loadNotifications();
  setInterval(loadNotifications, 15000);
});

// =========================================================
// FEED SYSTEM
// =========================================================

const feedList = document.getElementById("feed-list");
const postBtn = document.getElementById("composer-post-btn");

const titleInput = document.getElementById("composer-title");
const contentInput = document.getElementById("composer-content");
const ratingSelect = document.getElementById("composer-rating");

document.addEventListener("DOMContentLoaded", loadFeed);

async function loadFeed() {
  feedList.innerHTML = "<p>Loading feed...</p>";

  const res = await fetch("/api/post_fetch.php");
  const posts = await res.json();

  feedList.innerHTML = "";

  posts.forEach(post => {
    feedList.appendChild(renderPost(post));
  });
}

function renderPost(post) {
  const div = document.createElement("div");
  div.className = "post card";

  div.innerHTML = `
    <h3>${post.title || "Untitled"}</h3>
    <p>${post.content}</p>
    <p class="post-meta">Rating: ${post.rating || "N/A"} • ${post.created_at}</p>
  `;

  return div;
}

// =========================================================
// SUBMIT POST
// =========================================================

postBtn.addEventListener("click", async () => {
  const title = titleInput.value.trim();
  const content = contentInput.value.trim();
  const rating = ratingSelect.value;

  if (!title && !content) {
    alert("Write something first.");
    return;
  }

  const formData = new FormData();
  formData.append("title", title);
  formData.append("content", content);
  formData.append("rating", rating);

  postBtn.disabled = true;
  postBtn.textContent = "Posting...";

  const res = await fetch("/api/post_create.php", {
    method: "POST",
    body: formData
  });

  const data = await res.json();

  postBtn.disabled = false;
  postBtn.textContent = "Post";

  if (data.status === "success") {
    titleInput.value = "";
    contentInput.value = "";
    ratingSelect.value = "";
    loadFeed();
  } else {
    alert("Error posting.");
  }
});

// =========================================================
// PROFILE EDITING DRAWER
// =========================================================

const profileEditDrawer = document.getElementById("profile-edit-drawer");
const profileEditForm = document.getElementById("profile-edit-form");

document.querySelector("#profile-drawer .btn-primary.full-width")
  .addEventListener("click", () => {
    profileEditDrawer.classList.add("open");
  });

profileEditForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const formData = new FormData(profileEditForm);
  formData.append("city", document.getElementById("edit-city").value);
  formData.append("state", document.getElementById("edit-state").value);

  const res = await fetch("/api/profile_update.php", {
    method: "POST",
    body: formData
  });

  const data = await res.json();

  if (data.status === "success") {
    document.querySelector(".profile-name").textContent = formData.get("full_name");
    document.querySelector(".profile-bio").textContent = formData.get("bio");
    document.querySelector(".profile-city").textContent = formData.get("city");
    document.querySelector(".profile-state").textContent = formData.get("state");

    if (data.avatar) {
      document.querySelector(".profile-avatar-large").style.backgroundImage = `url(${data.avatar})`;
    }

    profileEditDrawer.classList.remove("open");
  } else {
    alert("Could not update profile.");
  }
});

// =========================================================
// ACHIEVEMENTS SYSTEM
// =========================================================

const achievementDrawer = document.getElementById("achievement-drawer");
const achievementForm = document.getElementById("achievement-form");
const achievementList = document.getElementById("achievement-list");
const btnAddAchievement = document.getElementById("btn-add-achievement");

btnAddAchievement.addEventListener("click", () => {
  achievementDrawer.classList.add("open");
});

async function loadAchievements() {
  const res = await fetch("/api/achievement_fetch.php");
  const achievements = await res.json();

  achievementList.innerHTML = "";

  achievements.forEach(a => {
    const badge = document.createElement("div");
    badge.className = "achievement-badge";
    badge.style.background = a.badge_color;
    badge.textContent = `${a.category}: ${a.title}`;
    achievementList.appendChild(badge);
  });
}

document.addEventListener("DOMContentLoaded", loadAchievements);

achievementForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const formData = new FormData(achievementForm);

  const res = await fetch("/api/achievement_add.php", {
    method: "POST",
    body: formData
  });

  const data = await res.json();

  if (data.status === "success") {
    achievementDrawer.classList.remove("open");
    achievementForm.reset();
    loadAchievements();
  } else {
    alert("Could not save achievement.");
  }
});
