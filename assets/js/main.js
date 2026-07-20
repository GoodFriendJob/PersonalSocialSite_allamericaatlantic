const appShell = document.querySelector('.app-shell[data-view="main"]');
const profileShell = document.querySelector('.profile-shell[data-view="profile"]');

/** Application root path, e.g. /AmericanNetwork (set in app.php) */
function appUrl(relPath) {
  const base = (window.__APP_BASE__ || "").replace(/\/$/, "");
  const path = String(relPath).replace(/^\//, "");
  return base ? `${base}/${path}` : `/${path}`;
}

let sidebarUserCache = null;

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function hydrateMentions() {
  document.querySelectorAll(".js-link-mentions").forEach((el) => {
    const text = el.textContent;
    if (!/@\S/.test(text)) return;
    el.innerHTML = escapeHtml(text).replace(/@([^\s@]+)/g, (_, user) => {
      const href = appUrl(`profile.php?u=${encodeURIComponent(user)}`);
      return `<a href="${escapeHtml(href)}" class="mention-link">@${escapeHtml(user)}</a>`;
    });
  });
}

function setAppStage(stage) {
  /* Keep `.app-shell` visible: profile UI lives inside it. When editing, collapse feed columns. */
  if (appShell) appShell.style.display = "block";
  const isProfile = stage === "profile";
  if (profileShell) profileShell.style.display = isProfile ? "block" : "none";
  document.querySelector(".sidebar-left")?.style.setProperty("display", isProfile ? "none" : "");
  document.querySelector(".center-feed")?.style.setProperty("display", isProfile ? "none" : "");
  document.querySelector(".sidebar-right")?.style.setProperty("display", isProfile ? "none" : "");
}

const params = new URLSearchParams(window.location.search);
setAppStage(params.get("stage") === "profile" ? "profile" : "main");
const primaryViewSelect = document.getElementById("primary-view-select");

function setView(view) {
  if (primaryViewSelect) {
    primaryViewSelect.value = view;
  }

  document.querySelectorAll("[data-view]").forEach((el) => {
    if (el === appShell || el === profileShell) return;
    const elView = el.getAttribute("data-view");
    if (!elView) return;
    el.style.display = elView === view ? "" : "none";
  });
}

if (primaryViewSelect) {
  primaryViewSelect.addEventListener("change", (event) => {
    const view = event.target.value || "general";
    setAppStage("main");
    setView(view);
  });
}
setView("general");

function populateProfileForm(u) {
  const setVal = (id, v) => {
    const el = document.getElementById(id);
    if (el) el.value = v ?? "";
  };
  setVal("profile-first-name", u.first_name || "");
  setVal("profile-last-name", u.last_name || "");
  setVal("profile-username", u.username || "");
  setVal("profile-sport", u.sport || "");
  setVal("profile-position", u.position || "");
  setVal("profile-city", u.city || "");
  setVal("profile-state", u.state || "");
  setVal("profile-bio", u.bio || "");
  setVal("profile-goals-input", u.goals || "");

  const picPreview = document.getElementById("profile-picture-preview");
  const picInput = document.getElementById("profile-picture");
  const removePic = document.getElementById("profile-remove-picture");
  if (picPreview) picPreview.src = u.profile_pic || "assets/img/charles.jpg";
  if (picInput) picInput.value = "";
  if (removePic) removePic.checked = false;
}

const profileForm = document.getElementById("profile-form");
const profileFormError = document.getElementById("profile-form-error");
const profilePictureInput = document.getElementById("profile-picture");
const profilePicturePreview = document.getElementById("profile-picture-preview");
const profileRemovePicture = document.getElementById("profile-remove-picture");

if (profilePictureInput && profilePicturePreview) {
  profilePictureInput.addEventListener("change", (event) => {
    const file = event.target.files && event.target.files[0];
    if (!file) {
      if (sidebarUserCache?.profile_pic) {
        profilePicturePreview.src = sidebarUserCache.profile_pic;
      } else {
        profilePicturePreview.src = "assets/img/charles.jpg";
      }
      return;
    }
    if (profileRemovePicture) profileRemovePicture.checked = false;
    if (!file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      profilePicturePreview.src = e.target?.result || "assets/img/charles.jpg";
    };
    reader.readAsDataURL(file);
  });
}

if (profileRemovePicture && profilePicturePreview) {
  profileRemovePicture.addEventListener("change", () => {
    if (profileRemovePicture.checked) {
      profilePicturePreview.src = "assets/img/charles.jpg";
      if (profilePictureInput) profilePictureInput.value = "";
    } else if (sidebarUserCache?.profile_pic) {
      profilePicturePreview.src = sidebarUserCache.profile_pic;
    }
  });
}

if (profileForm) {
  profileForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (profileFormError) {
      profileFormError.style.display = "none";
      profileFormError.textContent = "";
    }

    const payload = {
      first_name: document.getElementById("profile-first-name")?.value.trim() ?? "",
      last_name: document.getElementById("profile-last-name")?.value.trim() ?? "",
      username: document.getElementById("profile-username")?.value.trim() ?? "",
      sport: document.getElementById("profile-sport")?.value.trim() ?? "",
      position: document.getElementById("profile-position")?.value.trim() ?? "",
      city: document.getElementById("profile-city")?.value.trim() ?? "",
      state: document.getElementById("profile-state")?.value.trim() ?? "",
      bio: document.getElementById("profile-bio")?.value.trim() ?? "",
      goals: document.getElementById("profile-goals-input")?.value.trim() ?? "",
      remove_picture: document.getElementById("profile-remove-picture")?.checked ? "1" : "0",
    };

    const formData = new FormData();
    Object.entries(payload).forEach(([k, v]) => formData.append(k, v));
    const pictureFile = profilePictureInput?.files && profilePictureInput.files[0];
    if (pictureFile) {
      formData.append("profile_picture", pictureFile);
    }

    try {
      const res = await fetch(appUrl("app/update_profile.php"), {
        method: "POST",
        body: formData,
      });
      const data = await res.json();
      if (!data.success) {
        if (profileFormError) {
          profileFormError.textContent = data.message || "Could not save profile.";
          profileFormError.style.display = "";
        }
        return;
      }
      await loadSidebarProfile();
      setAppStage("main");
    } catch (err) {
      console.error(err);
      if (profileFormError) {
        profileFormError.textContent = "Network error while saving.";
        profileFormError.style.display = "";
      }
    }
  });
}

const editProfileBtn = document.getElementById("btn-edit-profile");
if (editProfileBtn)
  editProfileBtn.addEventListener("click", async () => {
    await loadSidebarProfile();
    if (sidebarUserCache) populateProfileForm(sidebarUserCache);
    setAppStage("profile");
  });

const generalTabs = document.querySelectorAll(".general-tab");
generalTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    generalTabs.forEach((t) => t.classList.remove("is-active"));
    tab.classList.add("is-active");
  });
});

const sportsTabs = document.querySelectorAll(".sports-tab");
const sportsSubtabs = document.querySelectorAll(".sports-subtab");
const horseSubcategoriesContainer = document.querySelector(".sports-subcategories");

function filterSportsPosts(sport) {
  document.querySelectorAll(".post[data-sport]").forEach((post) => {
    const postSport = post.getAttribute("data-sport");
    post.style.display = sport === "all" || sport === postSport ? "" : "none";
  });
  if (horseSubcategoriesContainer) {
    horseSubcategoriesContainer.style.display = sport === "horse-racing" ? "flex" : "none";
  }
}

sportsTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    sportsTabs.forEach((t) => t.classList.remove("is-active"));
    tab.classList.add("is-active");
    filterSportsPosts(tab.dataset.sport || "all");
  });
});

sportsSubtabs.forEach((subtab) => {
  subtab.addEventListener("click", () => {
    sportsSubtabs.forEach((s) => s.classList.remove("is-active"));
    subtab.classList.add("is-active");
  });
});
filterSportsPosts("all");

const postMediaInput = document.getElementById("post-media");
const fileLabel = document.getElementById("file-label");
const mediaPreview = document.getElementById("media-preview");

if (postMediaInput && fileLabel && mediaPreview) {
  postMediaInput.addEventListener("change", (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) {
      fileLabel.textContent = "Upload video, photos, or moments";
      mediaPreview.innerHTML = "";
      return;
    }
    fileLabel.textContent = file.name;
    mediaPreview.innerHTML = "";
    const url = URL.createObjectURL(file);
    let el = null;
    if (file.type.startsWith("image/")) {
      el = document.createElement("img");
      el.src = url;
      el.alt = "Uploaded preview";
    } else if (file.type.startsWith("video/")) {
      el = document.createElement("video");
      el.src = url;
      el.controls = true;
    }
    if (el) {
      el.classList.add("media-preview-item");
      mediaPreview.appendChild(el);
    }
  });
}



document.querySelectorAll(".post-rating").forEach((ratingBlock) => {
  const stars = ratingBlock.querySelectorAll(".rating-star");
  stars.forEach((star) => {
    star.addEventListener("click", () => {
      const value = Number(star.dataset.value || "0");
      stars.forEach((s) => s.classList.toggle("is-active", Number(s.dataset.value || "0") <= value));
    });
  });
});

document.querySelectorAll(".comment-form").forEach((form) => {
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const input = form.querySelector('input[type="text"]');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    const commentsContainer = form.previousElementSibling;
    if (!commentsContainer || !commentsContainer.classList.contains("comments")) return;
    const comment = document.createElement("div");
    comment.className = "comment";
    comment.innerHTML = `<div class="avatar avatar-small">You</div><div><p class="comment-author">You</p><p class="comment-body">${text}</p></div>`;
    commentsContainer.appendChild(comment);
    input.value = "";
  });
});

function addRevealAttributes() {
  document.querySelectorAll(".main-nav, .sidebar, .card, .post, .friend").forEach((el, idx) => {
    if (!el.hasAttribute("data-reveal")) {
      el.setAttribute("data-reveal", "");
      el.style.transitionDelay = `${Math.min(idx * 40, 280)}ms`;
    }
  });
}

function setupRevealObserver() {
  addRevealAttributes();
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) entry.target.classList.add("in-view");
      });
    },
    { threshold: 0.12 }
  );

  document.querySelectorAll("[data-reveal]").forEach((el) => observer.observe(el));
}


setupRevealObserver();

// =====================
// LOAD LEFT SIDEBAR DATA
// =====================

document.addEventListener("DOMContentLoaded", async () => {
  await loadSidebarProfile();
  hydrateMentions();
});

async function loadSidebarProfile() {
  try {
    const res = await fetch(appUrl("app/me.php"));
    const data = await res.json();

    if (!data.success) {
      console.warn("Sidebar profile:", data.message || "failed");
      return;
    }

    const u = data.user;
    sidebarUserCache = u;

    const profilePic = document.getElementById("profilePic");
    const profileName = document.getElementById("profileName");
    const profileHandle = document.getElementById("profileHandle");
    const profileSport = document.getElementById("profileSport");
    const profileLocation = document.getElementById("profileLocation");
    const profileBio = document.getElementById("profileBio");
    const profileGoals = document.getElementById("profileGoals");
    const badgeLikes = document.getElementById("badgeLikes");
    const statHighlights = document.getElementById("statHighlights");
    const statScouts = document.getElementById("statScouts");
    const statRating = document.getElementById("statRating");

    const displayName = [u.first_name, u.last_name].filter(Boolean).join(" ").trim() || u.username || "Member";

    if (profilePic) profilePic.src = u.profile_pic || "assets/img/charles.jpg";
    if (profileName) profileName.textContent = displayName;

    if (profileHandle && u.username) {
      profileHandle.textContent = "@" + u.username;
      profileHandle.href = appUrl(`profile.php?u=${encodeURIComponent(u.username)}`);
    }

    const sportTxt = u.sport && String(u.sport).trim() ? u.sport : "Sport not set";
    const posTxt = u.position && String(u.position).trim() ? u.position : "Position not set";
    if (profileSport) profileSport.textContent = `${sportTxt} — ${posTxt}`;

    if (profileLocation) {
      profileLocation.textContent =
        u.city || u.state
          ? [u.city, u.state].filter(Boolean).join(", ")
          : "Not set yet";
    }

    if (profileBio) profileBio.textContent = u.bio && String(u.bio).trim() ? u.bio : "No bio yet.";

    if (profileGoals) {
      const g = u.goals && String(u.goals).trim();
      if (g) {
        profileGoals.innerHTML = "<strong>Goals:</strong> " + escapeHtml(g).replace(/\n/g, "<br>");
        profileGoals.hidden = false;
      } else {
        profileGoals.textContent = "";
        profileGoals.hidden = true;
      }
    }

    if (badgeLikes) badgeLikes.textContent = u.likes ?? 0;
    if (statHighlights) statHighlights.textContent = String(u.num_highlights ?? 0);
    if (statScouts) statScouts.textContent = String(u.num_saved_posts ?? 0);
    const ratingNum = u.rating !== undefined && u.rating !== null ? Number(u.rating) : 0;
    if (statRating) statRating.textContent = Number.isFinite(ratingNum) ? ratingNum.toFixed(1) : "0.0";
  } catch (err) {
    console.error("Sidebar load error:", err);
  }
}
// RIGHT SIDEBAR — LOAD FRIENDS LIST
async function loadFriends() {
    const res = await fetch('/app/get_friends.php');
    const data = await res.json();

    if (!data.success) return;

    const list = document.getElementById('friendsList');
    list.innerHTML = '';

    data.friends.forEach(friend => {
        const initials = (friend.first_name[0] + friend.last_name[0]).toUpperCase();

        list.innerHTML += `
            <li class="friend-row">
                <div class="avatar">${initials}</div>
                <div>
                    <p class="friend-name">${friend.first_name} ${friend.last_name}</p>
                    <p class="friend-status online">Online</p>
                </div>
            </li>
        `;
    });
}

document.querySelector('.add-friend-btn').addEventListener('click', async () => {
    const name = document.querySelector('.add-friend-card input[type="text"]').value.trim();

    if (!name) return;

    const res = await fetch('/app/add_friend.php', {
        method: 'POST',
        body: new URLSearchParams({ name })
    });

    const data = await res.json();

    if (data.success) {
        alert("Friend request sent");
    } else {
        alert(data.message);
    }
});

// add search 
const searchInput = document.querySelector('.add-friend-card input[type="text"]');
const searchResults = document.getElementById('searchResults');

searchInput.addEventListener('input', async () => {
    const q = searchInput.value.trim();

    if (q.length < 2) {
        searchResults.innerHTML = '';
        return;
    }

    const res = await fetch('/app/search_users.php?q=' + encodeURIComponent(q));
    const data = await res.json();

    if (!data.success) return;

    searchResults.innerHTML = '';

    data.results.forEach(user => {
        const row = document.createElement('div');
        row.className = 'search-row';
        row.innerHTML = `
            <div class="avatar">${user.first_name[0].toUpperCase()}</div>
            <div>
                <p class="friend-name">${user.first_name} ${user.last_name}</p>
                <p class="friend-status">@${user.username}</p>
            </div>
        `;
        row.addEventListener('click', () => {
            searchInput.value = user.username;
            searchResults.innerHTML = '';
        });
        searchResults.appendChild(row);
    });
});


document.addEventListener("DOMContentLoaded", loadFriends);
