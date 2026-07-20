/**
 * The logged-in feed: stories, composer, posts, comments and the
 * Comment / Share / Save / Message actions.
 *
 * Replaces the static mockup markup that used to sit in app.php — a hardcoded
 * "Jordan Walker" post and a stories panel that read "will show here". Every
 * element below is rendered from the API.
 */
(function () {
  "use strict";

  const storyBar = document.getElementById("story-bar");
  const feedList = document.getElementById("feed-list");
  const composer = document.getElementById("composer-form");
  const tabsEl = document.getElementById("category-tabs");
  const pagerEl = document.getElementById("feed-pagination");

  if (!feedList) return; // not on the app page

  let currentUser = null;
  let categories = [];

  // Active feed filter/page. activeCategory === null means "all".
  let activeCategory = null;
  let currentPage = 1;
  const PAGE_SIZE = 10;

  /* =====================================================================
     BOOT
  ===================================================================== */
  init();

  async function init() {
    try {
      const me = await Api.get("auth/me");
      currentUser = me.user;
    } catch (err) {
      // Session expired between page render and this call.
      window.location.href = "login.html";
      return;
    }

    await loadCategories();

    loadStories();
    loadFeed();
    wireComposer();
  }

  /* =====================================================================
     CATEGORIES
     The sport tabs used to be static HTML that filtered nothing. They are
     now rendered from the API and drive the feed's category_id filter.
  ===================================================================== */
  async function loadCategories() {
    try {
      const data = await Api.get("categories");
      categories = data.categories || [];
    } catch (err) {
      categories = [];
    }

    renderTabs();
    fillComposerCategories();
  }

  function renderTabs() {
    if (!tabsEl) return;

    const tabs = [{ id: null, name: "All" }].concat(categories);

    tabsEl.innerHTML = tabs
      .map(function (c) {
        const active = String(c.id) === String(activeCategory) ? " active" : "";
        const count = c.post_count ? ' <span class="tab-count">' + c.post_count + "</span>" : "";
        return (
          '<button class="tab' + active + '" type="button" data-category="' +
          (c.id === null ? "" : c.id) + '">' + Api.escape(c.name) + count + "</button>"
        );
      })
      .join("");

    tabsEl.querySelectorAll("[data-category]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const value = btn.dataset.category;
        activeCategory = value === "" ? null : Number(value);
        currentPage = 1;

        tabsEl.querySelectorAll(".tab").forEach(function (t) {
          t.classList.toggle("active", t === btn);
        });

        syncComposerCategory();
        loadFeed();
      });
    });
  }

  function fillComposerCategories() {
    const select = document.getElementById("composer-category");
    if (!select) return;

    select.innerHTML =
      '<option value="">No category</option>' +
      categories
        .map(function (c) {
          return '<option value="' + c.id + '">' + Api.escape(c.name) + "</option>";
        })
        .join("");

    syncComposerCategory();
  }

  /**
   * Keeps the composer's category locked to whichever tab is selected, so a
   * post written while browsing Football is filed under Football.
   *
   * The select is disabled while a tab is active — a disabled control submits
   * nothing, so the submit handler falls back to activeCategory.
   */
  function syncComposerCategory() {
    const select = document.getElementById("composer-category");
    const hint = document.getElementById("composer-category-hint");
    if (!select) return;

    if (activeCategory === null) {
      select.disabled = false;
      select.value = "";
      if (hint) hint.textContent = "";
      return;
    }

    select.value = String(activeCategory);
    select.disabled = true;

    if (hint) {
      hint.textContent = "Posting to " + categoryName(activeCategory);
    }
  }

  /* =====================================================================
     STORIES
  ===================================================================== */
  async function loadStories() {
    if (!storyBar) return;

    try {
      const data = await Api.get("stories");
      renderStories(data.stories || []);
    } catch (err) {
      storyBar.innerHTML = '<p class="feed-empty">Could not load stories.</p>';
    }
  }

  /**
   * The API returns one entry per user — {user_id, username, stories: [...]}
   * — not a flat list. One tile per user, newest story as the thumbnail.
   * Stories expire after 24h, so an empty bar is normal, not an error.
   */
  function renderStories(groups) {
    const addTile =
      '<button class="story-tile story-add" id="story-add-btn" type="button">' +
      '<span class="story-add-icon">+</span><span class="story-label">Add story</span></button>';

    if (!groups.length) {
      storyBar.innerHTML = addTile + '<p class="story-empty">No active stories — add the first one.</p>';
    } else {
      storyBar.innerHTML =
        addTile +
        groups
          .map(function (group, index) {
            const newest = (group.stories || [])[0];
            if (!newest) return "";

            const thumb = Api.assetUrl(newest.thumbnail_url || newest.media_url);
            const unseen = (group.stories || []).some(function (s) {
              return !s.seen;
            });

            return (
              '<button class="story-tile" type="button" data-group="' + index + '">' +
              '<img class="story-thumb' + (unseen ? " is-unseen" : "") + '" src="' +
                Api.escape(thumb) + '" alt="" loading="lazy">' +
              '<span class="story-label">' + Api.escape(group.username || "") + "</span>" +
              "</button>"
            );
          })
          .join("");
    }

    const addBtn = document.getElementById("story-add-btn");
    if (addBtn) addBtn.addEventListener("click", promptStoryUpload);

    storyBar.querySelectorAll("[data-group]").forEach(function (el) {
      el.addEventListener("click", function () {
        const group = groups[Number(el.dataset.group)];
        if (group && group.stories && group.stories.length) {
          openStoryViewer(group.stories, 0);
        }
      });
    });
  }

  function promptStoryUpload() {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*,video/*";

    input.addEventListener("change", async function () {
      if (!input.files.length) return;

      const fd = new FormData();
      fd.append("media", input.files[0]);
      fd.append("caption", "");

      try {
        await Api.upload("stories", fd);
        loadStories();
      } catch (err) {
        alert("Could not upload story: " + err.message);
      }
    });

    input.click();
  }

  /** Steps through one user's stories with arrow keys or on-screen controls. */
  function openStoryViewer(stories, startIndex) {
    let index = startIndex;

    const overlay = document.createElement("div");
    overlay.className = "story-viewer";
    document.body.appendChild(overlay);

    function close() {
      overlay.remove();
      document.removeEventListener("keydown", onKey);
    }

    function step(delta) {
      const next = index + delta;
      if (next < 0) return;
      if (next >= stories.length) return close();
      index = next;
      draw();
    }

    function onKey(e) {
      if (e.key === "Escape") close();
      if (e.key === "ArrowRight") step(1);
      if (e.key === "ArrowLeft") step(-1);
    }

    function draw() {
      const story = stories[index];
      const url = Api.assetUrl(story.media_url);
      const isVideo = String(story.media_type).toLowerCase() === "video";

      overlay.innerHTML =
        '<div class="story-viewer-inner">' +
          '<button class="story-viewer-close" type="button" aria-label="Close">&times;</button>' +
          (isVideo
            ? '<video src="' + Api.escape(url) + '" controls autoplay playsinline class="story-viewer-media"></video>'
            : '<img src="' + Api.escape(url) + '" alt="" class="story-viewer-media">') +
          '<p class="story-viewer-caption">' + Api.escape(story.caption || "") + "</p>" +
          '<p class="story-viewer-count">' + (index + 1) + " / " + stories.length + "</p>" +
          (index > 0 ? '<button class="story-nav story-prev" type="button" aria-label="Previous">&#8249;</button>' : "") +
          (index < stories.length - 1 ? '<button class="story-nav story-next" type="button" aria-label="Next">&#8250;</button>' : "") +
        "</div>";

      // Best effort — a failed view count should not break playback.
      Api.post("stories/" + story.id + "/view").catch(function () {});
    }

    overlay.addEventListener("click", function (e) {
      if (e.target === overlay || e.target.classList.contains("story-viewer-close")) return close();
      if (e.target.classList.contains("story-prev")) step(-1);
      if (e.target.classList.contains("story-next")) step(1);
    });

    document.addEventListener("keydown", onKey);
    draw();
  }

  /* =====================================================================
     COMPOSER
  ===================================================================== */
  function wireComposer() {
    if (!composer) return;

    const fileInput = document.getElementById("composer-media");
    const preview = document.getElementById("composer-preview");

    if (fileInput && preview) {
      fileInput.addEventListener("change", function () {
        preview.innerHTML = "";
        Array.prototype.forEach.call(fileInput.files, function (file) {
          const url = URL.createObjectURL(file);
          const node = file.type.startsWith("video/")
            ? Object.assign(document.createElement("video"), { src: url, controls: true })
            : Object.assign(document.createElement("img"), { src: url, alt: "" });
          node.className = "composer-preview-item";
          preview.appendChild(node);
        });
      });
    }

    composer.addEventListener("submit", async function (e) {
      e.preventDefault();

      const contentEl = document.getElementById("composer-content");
      const content = contentEl.value.trim();
      const status = document.getElementById("composer-status");
      const submitBtn = composer.querySelector('[type="submit"]');

      if (!content) {
        showStatus(status, "Write something before posting.", true);
        return;
      }

      const fd = new FormData();
      fd.append("content", content);
      fd.append("title", (document.getElementById("composer-title") || {}).value || "");
      fd.append("visibility", (document.getElementById("composer-visibility") || {}).value || "public");
      // When a tab is active the select is locked to it, so prefer that.
      const categorySelect = document.getElementById("composer-category");
      const categoryId = activeCategory !== null
        ? String(activeCategory)
        : (categorySelect ? categorySelect.value : "");
      fd.append("category_id", categoryId);

      if (fileInput) {
        Array.prototype.forEach.call(fileInput.files, function (file) {
          if (file.type.startsWith("video/")) fd.append("video", file);
          else fd.append("images[]", file);
        });
      }

      submitBtn.disabled = true;
      showStatus(status, "Posting…", false);

      try {
        await Api.upload("posts", fd);

        composer.reset();
        if (preview) preview.innerHTML = "";
        showStatus(status, "", false);

        // composer.reset() clears the locked category, so restore it.
        syncComposerCategory();

        // Jump back to page 1 so the new post is visible.
        currentPage = 1;
        loadCategories(); // refresh the per-category counts
        loadFeed();
      } catch (err) {
        showStatus(status, err.message, true);
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  function showStatus(el, message, isError) {
    if (!el) return;
    el.textContent = message;
    el.className = "composer-status" + (isError ? " is-error" : "");
    el.style.display = message ? "block" : "none";
  }

  /* =====================================================================
     FEED
  ===================================================================== */
  async function loadFeed() {
    feedList.innerHTML = '<p class="feed-loading">Loading posts…</p>';
    if (pagerEl) pagerEl.innerHTML = "";

    const query = { page: currentPage, limit: PAGE_SIZE };
    if (activeCategory !== null) query.category_id = activeCategory;

    try {
      const data = await Api.get("posts", query);
      const posts = data.posts || [];

      if (!posts.length) {
        const label = categoryName(activeCategory);
        feedList.innerHTML =
          '<p class="feed-empty">' +
          (activeCategory === null
            ? "No posts yet. Be the first to share something."
            : "No posts in " + Api.escape(label) + " yet.") +
          "</p>";
        renderPager(data);
        return;
      }

      feedList.innerHTML = posts.map(renderPost).join("");
      posts.forEach(function (p) {
        wirePost(p.id);
      });

      renderPager(data);
      feedList.scrollIntoView({ behavior: "smooth", block: "nearest" });
    } catch (err) {
      feedList.innerHTML = '<p class="feed-empty">Could not load the feed: ' + Api.escape(err.message) + "</p>";
    }
  }

  function categoryName(id) {
    const match = categories.find(function (c) {
      return String(c.id) === String(id);
    });
    return match ? match.name : "this category";
  }

  /** Prev / next controls plus a page indicator. */
  function renderPager(data) {
    if (!pagerEl) return;

    const totalPages = data.total_pages || 0;

    if (totalPages <= 1) {
      pagerEl.innerHTML = "";
      return;
    }

    pagerEl.innerHTML =
      '<button class="pager-btn" type="button" data-page="prev"' +
        (currentPage <= 1 ? " disabled" : "") + ">Previous</button>" +
      '<span class="pager-status">Page ' + currentPage + " of " + totalPages +
        ' <span class="pager-total">(' + data.total + " posts)</span></span>" +
      '<button class="pager-btn" type="button" data-page="next"' +
        (data.has_more ? "" : " disabled") + ">Next</button>";

    pagerEl.querySelectorAll("[data-page]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (btn.disabled) return;
        currentPage += btn.dataset.page === "next" ? 1 : -1;
        if (currentPage < 1) currentPage = 1;
        loadFeed();
      });
    });
  }

  function renderPost(post) {
    const avatar = post.profile_pic
      ? '<img class="post-avatar" src="' + Api.escape(Api.assetUrl(post.profile_pic)) + '" alt="">'
      : '<div class="avatar">' + Api.escape((post.username || "?").slice(0, 2).toUpperCase()) + "</div>";

    const media = []
      .concat(
        (post.images || []).map(function (img) {
          return '<img class="post-media" loading="lazy" src="' + Api.escape(Api.assetUrl(img.image_url)) + '" alt="">';
        }),
        (post.videos || []).map(function (vid) {
          return '<video class="post-media" controls preload="metadata" src="' + Api.escape(Api.assetUrl(vid.video_url)) + '"></video>';
        })
      )
      .join("");

    return (
      '<article class="post-card" data-post-id="' + post.id + '">' +
        '<header class="post-header">' +
          '<div class="post-user">' + avatar +
            "<div><h3 class=\"post-author\">" + Api.escape(post.username || "Unknown") + "</h3>" +
            '<p class="post-meta">' + Api.escape(Api.timeAgo(post.created_at)) +
              (post.visibility === "private" ? ' · <span class="post-private">Private</span>' : "") +
            "</p></div>" +
          "</div>" +
          (post.category_name ? '<span class="post-tag">' + Api.escape(post.category_name) + "</span>" : "") +
        "</header>" +

        (post.title ? '<h4 class="post-title">' + Api.escape(post.title) + "</h4>" : "") +
        '<p class="post-caption">' + Api.escape(post.content || "") + "</p>" +
        (media ? '<div class="post-media-wrapper">' + media + "</div>" : "") +

        '<div class="post-counts">' +
          '<span data-count="likes">' + post.likes + " likes</span>" +
          '<span data-count="comments">' + post.comment_count + " comments</span>" +
          '<span data-count="shares">' + post.share_count + " shares</span>" +
        "</div>" +

        '<div class="post-actions">' +
          '<button class="post-btn' + (post.is_liked ? " is-active" : "") + '" data-action="like" type="button">' +
            (post.is_liked ? "Liked" : "Like") + "</button>" +
          '<button class="post-btn" data-action="comment" type="button">Comment</button>' +
          '<button class="post-btn" data-action="share" type="button">Share</button>' +
          '<button class="post-btn' + (post.is_saved ? " is-active" : "") + '" data-action="save" type="button">' +
            (post.is_saved ? "Saved" : "Save") + "</button>" +
          (post.is_mine
            ? ""
            : '<button class="post-btn" data-action="message" data-user-id="' + post.user_id + '" type="button">Message</button>') +
        "</div>" +

        '<div class="post-comments" data-comments hidden></div>' +

        '<form class="comment-form" data-comment-form hidden>' +
          '<input type="text" name="comment" placeholder="Write a comment…" autocomplete="off" required>' +
          "<button type=\"submit\">Post</button>" +
        "</form>" +
      "</article>"
    );
  }

  function wirePost(postId) {
    const card = feedList.querySelector('[data-post-id="' + postId + '"]');
    if (!card) return;

    card.querySelectorAll("[data-action]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const action = btn.dataset.action;
        if (action === "like") toggleLike(card, btn);
        if (action === "comment") toggleComments(card);
        if (action === "share") sharePost(card, btn);
        if (action === "save") toggleSave(card, btn);
        if (action === "message") messageUser(btn.dataset.userId);
      });
    });

    const form = card.querySelector("[data-comment-form]");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      submitComment(card, form);
    });
  }

  /* ---------------------------- LIKE ---------------------------- */
  async function toggleLike(card, btn) {
    const postId = card.dataset.postId;
    const liked = btn.classList.contains("is-active");

    btn.disabled = true;
    try {
      if (liked) await Api.del("posts/" + postId + "/like");
      else await Api.post("posts/" + postId + "/like");

      btn.classList.toggle("is-active", !liked);
      btn.textContent = liked ? "Like" : "Liked";
      bumpCount(card, "likes", liked ? -1 : 1, "likes");
    } catch (err) {
      alert("Could not update like: " + err.message);
    } finally {
      btn.disabled = false;
    }
  }

  /* ---------------------------- SAVE ---------------------------- */
  async function toggleSave(card, btn) {
    const postId = card.dataset.postId;
    const saved = btn.classList.contains("is-active");

    btn.disabled = true;
    try {
      if (saved) await Api.del("posts/" + postId + "/save");
      else await Api.post("posts/" + postId + "/save");

      btn.classList.toggle("is-active", !saved);
      btn.textContent = saved ? "Save" : "Saved";
    } catch (err) {
      alert("Could not update saved posts: " + err.message);
    } finally {
      btn.disabled = false;
    }
  }

  /* ---------------------------- SHARE --------------------------- */
  async function sharePost(card, btn) {
    const postId = card.dataset.postId;
    const note = prompt("Add a note to your share (optional):", "");
    if (note === null) return; // cancelled

    btn.disabled = true;
    try {
      const res = await Api.post("posts/" + postId + "/share", { comment: note });
      setCount(card, "shares", res.share_count, "shares");
      btn.textContent = "Shared";
      setTimeout(function () {
        btn.textContent = "Share";
      }, 1500);
    } catch (err) {
      alert("Could not share: " + err.message);
    } finally {
      btn.disabled = false;
    }
  }

  /* --------------------------- MESSAGE -------------------------- */
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

  /* -------------------------- COMMENTS -------------------------- */
  async function toggleComments(card) {
    const box = card.querySelector("[data-comments]");
    const form = card.querySelector("[data-comment-form]");
    const showing = !box.hidden;

    if (showing) {
      box.hidden = true;
      form.hidden = true;
      return;
    }

    box.hidden = false;
    form.hidden = false;
    box.innerHTML = '<p class="comment-loading">Loading comments…</p>';

    try {
      const data = await Api.get("posts/" + card.dataset.postId + "/comments");
      renderComments(box, data.comments || []);
    } catch (err) {
      box.innerHTML = '<p class="comment-loading">Could not load comments.</p>';
    }
  }

  function renderComments(box, comments) {
    if (!comments.length) {
      box.innerHTML = '<p class="comment-loading">No comments yet.</p>';
      return;
    }

    box.innerHTML = comments.map(renderComment).join("");
  }

  function renderComment(c) {
    const replies = (c.replies || []).map(renderComment).join("");

    return (
      '<div class="comment-row">' +
        '<div class="avatar-small">' + Api.escape((c.username || "?").slice(0, 2).toUpperCase()) + "</div>" +
        "<div class=\"comment-body\">" +
          '<p class="comment-author">' + Api.escape(c.username || "") +
            ' <span class="comment-time">' + Api.escape(Api.timeAgo(c.created_at)) + "</span></p>" +
          '<p class="comment-text">' + Api.escape(c.comment || "") + "</p>" +
          (replies ? '<div class="comment-replies">' + replies + "</div>" : "") +
        "</div>" +
      "</div>"
    );
  }

  async function submitComment(card, form) {
    const input = form.querySelector('input[name="comment"]');
    const text = input.value.trim();
    if (!text) return;

    const btn = form.querySelector("button");
    btn.disabled = true;

    try {
      await Api.post("posts/" + card.dataset.postId + "/comments", { comment: text });
      input.value = "";

      const data = await Api.get("posts/" + card.dataset.postId + "/comments");
      renderComments(card.querySelector("[data-comments]"), data.comments || []);
      bumpCount(card, "comments", 1, "comments");
    } catch (err) {
      alert("Could not post comment: " + err.message);
    } finally {
      btn.disabled = false;
    }
  }

  /* --------------------------- HELPERS -------------------------- */
  function bumpCount(card, key, delta, label) {
    const el = card.querySelector('[data-count="' + key + '"]');
    if (!el) return;
    const current = parseInt(el.textContent, 10) || 0;
    setCount(card, key, Math.max(0, current + delta), label);
  }

  function setCount(card, key, value, label) {
    const el = card.querySelector('[data-count="' + key + '"]');
    if (el) el.textContent = value + " " + label;
  }
})();
