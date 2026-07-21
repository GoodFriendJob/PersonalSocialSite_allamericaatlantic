(function () {
  "use strict";

  const form = document.getElementById("daily-post-form");
  const contentInput = document.getElementById("daily-post-content");
  const imageInput = document.getElementById("daily-post-images");
  const videoInput = document.getElementById("daily-post-video");
  const preview = document.getElementById("daily-post-preview");
  const status = document.getElementById("daily-post-status");
  const submit = document.getElementById("daily-post-submit");
  const list = document.getElementById("daily-post-list");
  const refresh = document.getElementById("daily-feed-refresh");
  const featuredPost = document.querySelector(".featured-post-card");
  const sportTabs = document.querySelectorAll(".sports-tabs .tab");
  if (!form || !list) return;

  function apiUrl(route) {
    const separator = route.indexOf("?");
    const path = separator === -1 ? route : route.slice(0, separator);
    const query = separator === -1 ? "" : `&${route.slice(separator + 1)}`;
    return appUrl(`app/index.php?route=${encodeURIComponent(path)}${query}`);
  }

  async function apiRequest(route, options) {
    const response = await fetch(apiUrl(route), { credentials: "same-origin", ...(options || {}) });
    const raw = await response.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch (_) {
      throw new Error("The post server returned an invalid response.");
    }
    if (!response.ok || data.error || data.success === false) {
      throw new Error(data.error || data.message || "The post request failed.");
    }
    return data;
  }

  function assetUrl(path) {
    if (!path) return "";
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith("/post_images/") || path.startsWith("/post_videos/")) {
      return appUrl(`app/public${path}`);
    }
    return appUrl(path.replace(/^\//, ""));
  }

  function initials(username) {
    return String(username || "Member").slice(0, 2).toUpperCase();
  }

  function avatar(username, picture, small) {
    if (picture) {
      const image = document.createElement("img");
      image.className = small ? "post-avatar post-avatar-small" : "post-avatar";
      image.src = assetUrl(picture);
      image.alt = "";
      return image;
    }
    const fallback = document.createElement("div");
    fallback.className = small ? "avatar avatar-small" : "avatar";
    fallback.textContent = initials(username);
    return fallback;
  }

  function timeAgo(value) {
    const date = new Date(String(value).replace(" ", "T"));
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (!Number.isFinite(seconds)) return "Recently";
    if (seconds < 60) return "Just now";
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
  }

  function renderComment(comment, nested) {
    const row = document.createElement("div");
    row.className = `daily-comment${nested ? " daily-comment-reply" : ""}`;
    row.appendChild(avatar(comment.username, comment.profile_pic, true));
    const body = document.createElement("div");
    const author = document.createElement("strong");
    author.textContent = `@${comment.username || "member"}`;
    const text = document.createElement("p");
    text.textContent = comment.comment;
    body.append(author, text);
    row.appendChild(body);
    return row;
  }

  async function toggleComments(post, card) {
    const section = card.querySelector(".daily-comments");
    const commentsList = card.querySelector(".daily-comments-list");
    const opening = section.hidden;
    section.hidden = !opening;
    if (!opening || section.dataset.loaded === "true") return;
    commentsList.innerHTML = '<p class="daily-comment-message">Loading comments...</p>';
    try {
      const data = await apiRequest(`posts/${post.id}/comments`);
      commentsList.innerHTML = "";
      if (!data.comments.length) {
        commentsList.innerHTML = '<p class="daily-comment-message">No comments yet. Start the conversation.</p>';
      } else {
        data.comments.forEach((comment) => {
          commentsList.appendChild(renderComment(comment, false));
          (comment.replies || []).forEach((reply) => commentsList.appendChild(renderComment(reply, true)));
        });
      }
      section.dataset.loaded = "true";
    } catch (error) {
      commentsList.innerHTML = `<p class="daily-comment-message post-error"></p>`;
      commentsList.firstElementChild.textContent = error.message;
    }
  }

  function renderPost(post) {
    const card = document.createElement("article");
    card.className = "post-card daily-post-card";
    card.id = `post-${post.id}`;
    card.dataset.sport = String(post.sport || "").toLowerCase();

    const header = document.createElement("header");
    header.className = "post-header";
    const user = document.createElement("div");
    user.className = "post-user";
    user.appendChild(avatar(post.username, post.profile_pic));
    const identity = document.createElement("div");
    const author = document.createElement("h3");
    author.className = "post-author";
    author.textContent = post.username || "Member";
    const meta = document.createElement("p");
    meta.className = "post-meta";
    meta.textContent = [post.sport, post.position, timeAgo(post.created_at)].filter(Boolean).join(" · ");
    identity.append(author, meta);
    user.appendChild(identity);
    header.appendChild(user);
    header.appendChild(ratingBadge(post));
    card.appendChild(header);

    if (post.content) {
      const text = document.createElement("p");
      text.className = "post-caption";
      text.textContent = post.content;
      card.appendChild(text);
    }

    const mediaItems = [];
    (post.images || []).forEach((image) => {
      const element = document.createElement("img");
      element.src = assetUrl(image.image_url);
      element.alt = post.content || "Daily post photo";
      element.loading = "lazy";
      mediaItems.push(element);
    });
    (post.videos || []).forEach((video) => {
      const element = document.createElement("video");
      element.src = assetUrl(video.video_url);
      element.controls = true;
      element.playsInline = true;
      element.preload = "metadata";
      if (video.thumbnail_url) element.poster = assetUrl(video.thumbnail_url);
      mediaItems.push(element);
    });
    if (mediaItems.length) {
      const media = document.createElement("div");
      media.className = `daily-post-media daily-post-media-${Math.min(mediaItems.length, 4)}`;
      mediaItems.forEach((element) => media.appendChild(element));
      card.appendChild(media);
    }

    if (window.AAARatings) {
      card.appendChild(window.AAARatings.createWidget({
        type: "post",
        id: post.id,
        average: post.rating,
        count: post.rating_count,
        myRating: post.my_rating,
        label: mediaItems.length ? "Rate this photo or video" : "Rate this post",
      }));
    }

    const actions = document.createElement("div");
    actions.className = "post-actions daily-post-actions";
    const like = actionButton(`${post.liked_by_me ? "Liked" : "Like"} · ${post.likes}`, post.liked_by_me ? "is-active" : "");
    like.addEventListener("click", async () => {
      like.disabled = true;
      try {
        const data = await apiRequest(`posts/${post.id}/like`, { method: post.liked_by_me ? "DELETE" : "POST" });
        post.liked_by_me = data.liked;
        post.likes = data.like_count;
        like.classList.toggle("is-active", data.liked);
        like.textContent = `${data.liked ? "Liked" : "Like"} · ${data.like_count}`;
      } catch (error) { showError(card, error.message); }
      finally { like.disabled = false; }
    });

    const commentsButton = actionButton(`Comment · ${post.comment_count}`);
    commentsButton.addEventListener("click", () => toggleComments(post, card));

    const share = actionButton("Share");
    share.addEventListener("click", async () => {
      const url = `${location.origin}${location.pathname}#post-${post.id}`;
      try {
        if (navigator.share) await navigator.share({ title: "All American Network", text: post.content, url });
        else {
          await navigator.clipboard.writeText(url);
          share.textContent = "Link copied";
          setTimeout(() => { share.textContent = "Share"; }, 1600);
        }
      } catch (_) { /* The member canceled sharing. */ }
    });

    const save = actionButton(post.saved_by_me ? "Saved" : "Save", post.saved_by_me ? "is-active" : "");
    save.addEventListener("click", async () => {
      save.disabled = true;
      try {
        const data = await apiRequest(`posts/${post.id}/save`, { method: post.saved_by_me ? "DELETE" : "POST" });
        post.saved_by_me = data.saved;
        save.textContent = data.saved ? "Saved" : "Save";
        save.classList.toggle("is-active", data.saved);
      } catch (error) { showError(card, error.message); }
      finally { save.disabled = false; }
    });

    const report = actionButton("Report");
    report.addEventListener("click", async () => {
      const reason = window.prompt("Tell the moderators why you are reporting this post:");
      if (!reason?.trim()) return;
      report.disabled = true;
      try {
        await apiRequest("reports", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ target_id: post.id, target_type: "post", reason: reason.trim() }),
        });
        report.textContent = "Reported";
      } catch (error) { showError(card, error.message); report.disabled = false; }
    });
    actions.append(like, commentsButton, share, save, report);

    if (Number(post.user_id) === Number(window.__CURRENT_USER_ID__)) {
      const remove = actionButton("Delete", "is-danger");
      remove.addEventListener("click", async () => {
        if (!window.confirm("Delete this post?")) return;
        try {
          await apiRequest(`posts/${post.id}`, { method: "DELETE" });
          card.remove();
        } catch (error) { showError(card, error.message); }
      });
      actions.appendChild(remove);
    }
    card.appendChild(actions);

    const comments = document.createElement("section");
    comments.className = "daily-comments";
    comments.hidden = true;
    const commentsList = document.createElement("div");
    commentsList.className = "daily-comments-list";
    const commentForm = document.createElement("form");
    commentForm.className = "daily-comment-form";
    const commentInput = document.createElement("input");
    commentInput.type = "text";
    commentInput.maxLength = 1000;
    commentInput.required = true;
    commentInput.placeholder = "Write a comment...";
    const commentSubmit = document.createElement("button");
    commentSubmit.type = "submit";
    commentSubmit.textContent = "Post";
    commentForm.append(commentInput, commentSubmit);
    commentForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const text = commentInput.value.trim();
      if (!text) return;
      commentSubmit.disabled = true;
      try {
        const data = await apiRequest(`posts/${post.id}/comments`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ comment: text }),
        });
        commentsList.querySelector(".daily-comment-message")?.remove();
        commentsList.appendChild(renderComment(data.comment, false));
        comments.dataset.loaded = "true";
        commentInput.value = "";
        post.comment_count += 1;
        commentsButton.textContent = `Comment · ${post.comment_count}`;
      } catch (error) { showError(card, error.message); }
      finally { commentSubmit.disabled = false; }
    });
    comments.append(commentsList, commentForm);
    card.appendChild(comments);

    const error = document.createElement("p");
    error.className = "daily-post-error post-error";
    error.hidden = true;
    card.appendChild(error);
    return card;
  }

  // Top-right community-rating badge for a post card. Shows the average as a
  // number with a star and rating count, or a "New" pill when unrated.
  function ratingBadge(post) {
    const average = Number(post.rating) || 0;
    const count = Number(post.rating_count) || 0;
    const badge = document.createElement("span");

    if (!average) {
      badge.className = "post-rating-badge is-unrated";
      badge.innerHTML = '<span class="prb-star">☆</span><span class="prb-score">New</span>';
      badge.title = "No community ratings yet — be the first to rate.";
      return badge;
    }

    const tier = average >= 4.5 ? "is-elite" : average >= 3.5 ? "is-strong" : "is-rising";
    badge.className = `post-rating-badge ${tier}`;
    badge.title = `Community rating: ${average.toFixed(1)} out of 5${count ? ` from ${count} rating${count === 1 ? "" : "s"}` : ""}`;

    const star = document.createElement("span");
    star.className = "prb-star";
    star.textContent = "★";
    const score = document.createElement("span");
    score.className = "prb-score";
    score.textContent = average.toFixed(1);
    badge.append(star, score);

    if (count) {
      const total = document.createElement("span");
      total.className = "prb-count";
      total.textContent = count >= 1000 ? `${(count / 1000).toFixed(1)}k` : String(count);
      badge.appendChild(total);
    }
    return badge;
  }

  function actionButton(label, extraClass) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `post-btn${extraClass ? ` ${extraClass}` : ""}`;
    button.textContent = label;
    return button;
  }

  function showError(card, message) {
    const error = card.querySelector(".daily-post-error");
    if (!error) return;
    error.textContent = message;
    error.hidden = false;
  }

  let currentSport = "all sports";
  function applySportFilter() {
    list.querySelectorAll(".daily-post-card").forEach((card) => {
      card.hidden = currentSport !== "all sports" && card.dataset.sport !== currentSport;
    });
    if (featuredPost) featuredPost.hidden = currentSport !== "all sports" && currentSport !== "football";
  }

  sportTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      sportTabs.forEach((item) => item.classList.remove("active"));
      tab.classList.add("active");
      currentSport = tab.textContent.trim().toLowerCase();
      applySportFilter();
    });
  });

  let loading = false;
  async function loadPosts() {
    if (loading) return;
    loading = true;
    refresh.disabled = true;
    list.innerHTML = '<p class="daily-feed-message">Loading daily posts...</p>';
    try {
      const data = await apiRequest("posts?limit=30");
      list.innerHTML = "";
      if (!data.posts.length) {
        list.innerHTML = '<p class="daily-feed-message">No daily posts yet. Share the first moment.</p>';
      } else {
        data.posts.forEach((post) => list.appendChild(renderPost(post)));
      }
      applySportFilter();
      if (/^#post-\d+$/.test(location.hash)) {
        requestAnimationFrame(() => document.querySelector(location.hash)?.scrollIntoView({ behavior: "smooth", block: "center" }));
      }
    } catch (error) {
      list.innerHTML = '<p class="daily-feed-message post-error"></p>';
      list.firstElementChild.textContent = error.message;
    } finally {
      loading = false;
      refresh.disabled = false;
    }
  }

  function showPreviews() {
    preview.innerHTML = "";
    const files = [...(imageInput.files || [])].slice(0, 6);
    if (videoInput.files?.[0]) files.push(videoInput.files[0]);
    preview.hidden = !files.length;
    files.forEach((file) => {
      const url = URL.createObjectURL(file);
      const media = document.createElement(file.type.startsWith("video/") ? "video" : "img");
      media.src = url;
      media.className = "daily-post-preview-item";
      if (media.tagName === "VIDEO") media.controls = true;
      media.addEventListener("loadeddata", () => URL.revokeObjectURL(url), { once: true });
      media.addEventListener("load", () => URL.revokeObjectURL(url), { once: true });
      preview.appendChild(media);
    });
  }
  imageInput.addEventListener("change", showPreviews);
  videoInput.addEventListener("change", showPreviews);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!contentInput.value.trim() && !imageInput.files.length && !videoInput.files.length) {
      status.textContent = "Write something or choose a photo or video.";
      status.className = "daily-post-status post-error";
      return;
    }
    submit.disabled = true;
    status.className = "daily-post-status";
    status.textContent = "Posting your moment...";
    const body = new FormData();
    body.append("content", contentInput.value.trim());
    [...imageInput.files].slice(0, 6).forEach((file) => body.append("images[]", file));
    if (videoInput.files[0]) body.append("video", videoInput.files[0]);
    try {
      await apiRequest("posts", { method: "POST", body });
      form.reset();
      preview.innerHTML = "";
      preview.hidden = true;
      status.className = "daily-post-status post-success";
      status.textContent = "Your daily post is live.";
      await loadPosts();
    } catch (error) {
      status.className = "daily-post-status post-error";
      status.textContent = error.message;
    } finally {
      submit.disabled = false;
    }
  });

  contentInput.addEventListener("input", () => {
    contentInput.style.height = "auto";
    contentInput.style.height = `${Math.min(contentInput.scrollHeight, 220)}px`;
  });
  refresh.addEventListener("click", loadPosts);
  loadPosts();
})();
