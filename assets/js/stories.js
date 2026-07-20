(function () {
  "use strict";

  const storiesPanel = document.querySelector(".stories-panel");
  const postsPanel = document.querySelector(".posts-panel");
  const reelsPanel = document.querySelector(".reels-panel");
  const panelButtons = document.querySelectorAll("[data-feed-panel]");
  const uploadForm = document.getElementById("story-upload-form");
  const mediaInput = document.getElementById("story-media");
  const captionInput = document.getElementById("story-caption");
  const preview = document.getElementById("story-preview");
  const uploadStatus = document.getElementById("story-upload-status");
  const submitButton = document.getElementById("story-submit");
  const refreshButton = document.getElementById("story-refresh");
  const feed = document.getElementById("story-feed");
  const rail = document.getElementById("story-rail");
  const reelsFeed = document.getElementById("reels-feed");
  const previousButton = document.getElementById("story-prev");
  const nextButton = document.getElementById("story-next");

  if (!storiesPanel || !feed || !rail) return;

  function apiUrl(route) {
    return appUrl(`app/index.php?route=${route}`);
  }

  function mediaUrl(path) {
    if (!path) return "";
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith("/uploads/stories/")) {
      return appUrl(`app/public${path}`);
    }
    return appUrl(path.replace(/^\//, ""));
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
      throw new Error("The story server returned an invalid response.");
    }
    if (!response.ok || data.error || data.success === false) {
      throw new Error(data.error || data.message || "The story request failed.");
    }
    return data;
  }

  function setPanel(name) {
    storiesPanel.style.display = name === "stories" ? "block" : "none";
    if (postsPanel) postsPanel.style.display = name === "posts" ? "flex" : "none";
    if (reelsPanel) reelsPanel.style.display = name === "reels" ? "block" : "none";
    panelButtons.forEach((button) => {
      const active = button.dataset.feedPanel === name;
      button.classList.toggle("active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  panelButtons.forEach((button) => {
    button.addEventListener("click", () => setPanel(button.dataset.feedPanel || "stories"));
  });

  function initials(username) {
    return String(username || "Member").slice(0, 2).toUpperCase();
  }

  function timeAgo(value) {
    const parsed = new Date(String(value).replace(" ", "T"));
    const seconds = Math.max(0, Math.floor((Date.now() - parsed.getTime()) / 1000));
    if (!Number.isFinite(seconds)) return "Recently";
    if (seconds < 60) return "Just now";
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
  }

  function createAvatar(username, picture) {
    if (picture) {
      const image = document.createElement("img");
      image.className = "story-avatar";
      image.src = mediaUrl(picture);
      image.alt = "";
      return image;
    }
    const avatar = document.createElement("div");
    avatar.className = "story-avatar story-avatar-fallback";
    avatar.textContent = initials(username);
    return avatar;
  }

  function renderComment(comment) {
    const row = document.createElement("div");
    row.className = "story-comment";
    row.appendChild(createAvatar(comment.username, comment.profile_pic));
    const body = document.createElement("div");
    const author = document.createElement("strong");
    author.textContent = `@${comment.username || "member"}`;
    const text = document.createElement("p");
    text.textContent = comment.comment;
    body.append(author, text);
    row.appendChild(body);
    return row;
  }

  async function openComments(story, card) {
    const section = card.querySelector(".story-comments");
    const list = card.querySelector(".story-comments-list");
    const isOpening = section.hidden;
    section.hidden = !isOpening;
    if (!isOpening || section.dataset.loaded === "true") return;

    list.innerHTML = '<p class="story-comment-message">Loading comments...</p>';
    try {
      const data = await apiRequest(`stories/${story.id}/comments`);
      list.innerHTML = "";
      if (!data.comments.length) {
        list.innerHTML = '<p class="story-comment-message">No comments yet. Start the conversation.</p>';
      } else {
        data.comments.forEach((comment) => list.appendChild(renderComment(comment)));
      }
      section.dataset.loaded = "true";
    } catch (error) {
      list.innerHTML = "";
      const message = document.createElement("p");
      message.className = "story-comment-message story-error";
      message.textContent = error.message;
      list.appendChild(message);
    }
  }

  function renderStory(story) {
    const card = document.createElement("article");
    card.className = `story-card${story.seen ? " is-seen" : ""}`;
    card.dataset.storyId = story.id;

    const header = document.createElement("header");
    header.className = "story-card-header";
    header.appendChild(createAvatar(story.username, story.profile_pic));
    const heading = document.createElement("div");
    const author = document.createElement("h3");
    author.textContent = `@${story.username || "member"}`;
    const meta = document.createElement("p");
    meta.textContent = `${timeAgo(story.created_at)} · expires in 24 hours`;
    heading.append(author, meta);
    header.appendChild(heading);
    card.appendChild(header);

    if (story.caption) {
      const caption = document.createElement("p");
      caption.className = "story-caption";
      caption.textContent = story.caption;
      card.appendChild(caption);
    }

    const mediaWrap = document.createElement("div");
    mediaWrap.className = "story-media-wrap";
    let media;
    if (story.media_type === "video") {
      media = document.createElement("video");
      media.controls = true;
      media.playsInline = true;
      media.preload = "metadata";
      if (story.thumbnail_url) media.poster = mediaUrl(story.thumbnail_url);
    } else {
      media = document.createElement("img");
      media.alt = story.caption || `Story from ${story.username}`;
      media.loading = "lazy";
    }
    media.className = "story-media";
    media.src = mediaUrl(story.media_url);
    media.addEventListener("play", () => markViewed(story, card), { once: true });
    media.addEventListener("click", () => markViewed(story, card), { once: true });
    mediaWrap.appendChild(media);
    card.appendChild(mediaWrap);

    if (window.AAARatings) {
      card.appendChild(window.AAARatings.createWidget({
        type: "story",
        id: story.id,
        average: story.average_rating,
        count: story.rating_count,
        myRating: story.my_rating,
        label: "Rate this story",
      }));
    }

    const actions = document.createElement("div");
    actions.className = "story-actions";
    const likeButton = document.createElement("button");
    likeButton.type = "button";
    likeButton.className = `story-action${story.liked_by_me ? " is-liked" : ""}`;
    likeButton.textContent = `${story.liked_by_me ? "Liked" : "Like"} · ${story.like_count}`;
    likeButton.addEventListener("click", async () => {
      likeButton.disabled = true;
      try {
        const method = story.liked_by_me ? "DELETE" : "POST";
        const data = await apiRequest(`stories/${story.id}/like`, { method });
        story.liked_by_me = data.liked;
        story.like_count = data.like_count;
        likeButton.classList.toggle("is-liked", data.liked);
        likeButton.textContent = `${data.liked ? "Liked" : "Like"} · ${data.like_count}`;
      } catch (error) {
        showCardError(card, error.message);
      } finally {
        likeButton.disabled = false;
      }
    });

    const commentButton = document.createElement("button");
    commentButton.type = "button";
    commentButton.className = "story-action";
    commentButton.textContent = `Comments · ${story.comment_count}`;
    commentButton.addEventListener("click", () => openComments(story, card));
    actions.append(likeButton, commentButton);
    card.appendChild(actions);

    const comments = document.createElement("section");
    comments.className = "story-comments";
    comments.hidden = true;
    const commentList = document.createElement("div");
    commentList.className = "story-comments-list";
    const form = document.createElement("form");
    form.className = "story-comment-form";
    const input = document.createElement("input");
    input.type = "text";
    input.maxLength = 1000;
    input.required = true;
    input.placeholder = "Write a comment...";
    input.setAttribute("aria-label", "Write a comment");
    const button = document.createElement("button");
    button.type = "submit";
    button.textContent = "Post";
    form.append(input, button);
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const text = input.value.trim();
      if (!text) return;
      button.disabled = true;
      try {
        const data = await apiRequest(`stories/${story.id}/comments`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ comment: text }),
        });
        commentList.querySelector(".story-comment-message")?.remove();
        commentList.appendChild(renderComment(data.comment));
        comments.dataset.loaded = "true";
        input.value = "";
        story.comment_count += 1;
        commentButton.textContent = `Comments · ${story.comment_count}`;
      } catch (error) {
        showCardError(card, error.message);
      } finally {
        button.disabled = false;
      }
    });
    comments.append(commentList, form);
    card.appendChild(comments);

    const error = document.createElement("p");
    error.className = "story-card-error";
    error.hidden = true;
    card.appendChild(error);
    return card;
  }

  function showCardError(card, message) {
    const error = card.querySelector(".story-card-error");
    if (!error) return;
    error.textContent = message;
    error.hidden = false;
  }

  async function markViewed(story, card) {
    if (story.seen) return;
    try {
      await apiRequest(`stories/${story.id}/view`, { method: "POST" });
      story.seen = true;
      card.classList.add("is-seen");
    } catch (_) {
      // Viewing should never interrupt playback.
    }
  }

  function renderStoryRail(groups) {
    rail.innerHTML = "";

    const createCard = document.createElement("button");
    createCard.type = "button";
    createCard.className = "story-rail-card story-create-card";
    createCard.innerHTML = '<span class="story-create-plus">+</span><strong>Create story</strong><small>Photo or video</small>';
    createCard.addEventListener("click", () => {
      setPanel("stories");
      uploadForm?.scrollIntoView({ behavior: "smooth", block: "center" });
      mediaInput?.click();
    });
    rail.appendChild(createCard);

    groups.forEach((group) => {
      const story = group.stories?.[0];
      if (!story) return;
      const card = document.createElement("button");
      card.type = "button";
      card.className = `story-rail-card${story.seen ? " is-seen" : ""}`;
      const cover = story.thumbnail_url || (story.media_type === "image" ? story.media_url : "");
      if (cover) card.style.backgroundImage = `linear-gradient(to top, rgba(2,4,11,.92), rgba(2,4,11,.05) 70%), url("${mediaUrl(cover).replace(/"/g, "%22")}")`;
      card.appendChild(createAvatar(group.username, group.profile_pic));
      const name = document.createElement("strong");
      name.textContent = group.username || "Member";
      const count = document.createElement("small");
      count.textContent = `${group.stories.length} ${group.stories.length === 1 ? "story" : "stories"}`;
      card.append(name, count);
      card.addEventListener("click", () => {
        setPanel("stories");
        requestAnimationFrame(() => {
          document.querySelector(`.story-card[data-story-id="${story.id}"]`)?.scrollIntoView({ behavior: "smooth", block: "center" });
        });
      });
      rail.appendChild(card);
    });

    previousButton.disabled = rail.scrollLeft <= 2;
    nextButton.disabled = rail.scrollWidth <= rail.clientWidth + 2;
  }

  let loadingStories = false;
  async function loadStories() {
    if (loadingStories) return;
    loadingStories = true;
    refreshButton?.setAttribute("disabled", "disabled");
    feed.innerHTML = '<p class="story-empty">Loading stories...</p>';
    rail.setAttribute("aria-busy", "true");
    try {
      const data = await apiRequest("stories");
      const groups = data.stories || [];
      const stories = groups.flatMap((group) =>
        (group.stories || []).map((story) => ({
          ...story,
          username: story.username || group.username,
          profile_pic: story.profile_pic || group.profile_pic,
        }))
      );
      renderStoryRail(groups);
      feed.innerHTML = "";
      if (!stories.length) {
        feed.innerHTML = '<p class="story-empty">No active stories yet. Be the first to share one.</p>';
      } else {
        stories.forEach((story) => feed.appendChild(renderStory(story)));
      }
      if (reelsFeed) {
        const reels = stories.filter((story) => story.media_type === "video");
        reelsFeed.innerHTML = "";
        if (!reels.length) {
          reelsFeed.innerHTML = '<p class="story-empty">No video reels are active in your network yet.</p>';
        } else {
          reels.forEach((story) => reelsFeed.appendChild(renderStory(story)));
        }
      }
    } catch (error) {
      feed.innerHTML = "";
      const message = document.createElement("p");
      message.className = "story-empty story-error";
      message.textContent = error.message;
      feed.appendChild(message);
      rail.innerHTML = '<p class="story-rail-loading story-error">Stories could not be loaded.</p>';
      if (reelsFeed) reelsFeed.innerHTML = '<p class="story-empty story-error">Reels could not be loaded.</p>';
    } finally {
      loadingStories = false;
      rail.removeAttribute("aria-busy");
      refreshButton?.removeAttribute("disabled");
    }
  }

  function inspectVideo(file) {
    return new Promise((resolve, reject) => {
      const video = document.createElement("video");
      const objectUrl = URL.createObjectURL(file);
      let finished = false;
      let fallbackTimer;
      const finish = () => {
        if (finished) return;
        finished = true;
        clearTimeout(fallbackTimer);
        let thumbnail = "";
        if (video.videoWidth && video.videoHeight && video.readyState >= 2) {
          const canvas = document.createElement("canvas");
          canvas.width = 480;
          canvas.height = Math.max(1, Math.round(video.videoHeight * (480 / video.videoWidth)));
          canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
          thumbnail = canvas.toDataURL("image/jpeg", 0.72);
        }
        URL.revokeObjectURL(objectUrl);
        resolve({ duration: Number.isFinite(video.duration) ? video.duration : "", thumbnail });
      };
      video.preload = "metadata";
      video.muted = true;
      video.playsInline = true;
      video.src = objectUrl;
      video.onloadedmetadata = () => {
        const duration = video.duration;
        if (Number.isFinite(duration) && duration > 15.25) {
          URL.revokeObjectURL(objectUrl);
          reject(new Error("Please choose a video that is 15 seconds or shorter."));
          return;
        }
        const captureAt = Math.min(1, Math.max(0, duration / 2));
        fallbackTimer = setTimeout(finish, 1500);
        if (captureAt > 0) video.currentTime = captureAt;
        else finish();
      };
      video.onseeked = finish;
      video.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        resolve({ duration: "", thumbnail: "" });
      };
    });
  }

  let selectedVideoData = { duration: "", thumbnail: "" };
  mediaInput?.addEventListener("change", async () => {
    const file = mediaInput.files?.[0];
    selectedVideoData = { duration: "", thumbnail: "" };
    preview.innerHTML = "";
    preview.hidden = !file;
    uploadStatus.textContent = "";
    if (!file) return;
    if (file.size > 25 * 1024 * 1024) {
      mediaInput.value = "";
      preview.hidden = true;
      uploadStatus.textContent = "Media must be smaller than 25 MB.";
      uploadStatus.className = "story-status story-error";
      return;
    }

    const objectUrl = URL.createObjectURL(file);
    let element;
    if (file.type.startsWith("video/")) {
      element = document.createElement("video");
      element.controls = true;
      element.muted = true;
      try {
        selectedVideoData = await inspectVideo(file);
      } catch (error) {
        URL.revokeObjectURL(objectUrl);
        mediaInput.value = "";
        preview.hidden = true;
        uploadStatus.textContent = error.message;
        uploadStatus.className = "story-status story-error";
        return;
      }
    } else {
      element = document.createElement("img");
      element.alt = "Story preview";
    }
    element.src = objectUrl;
    element.className = "story-preview-media";
    element.addEventListener("load", () => URL.revokeObjectURL(objectUrl), { once: true });
    preview.appendChild(element);
  });

  uploadForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const file = mediaInput.files?.[0];
    if (!file) return;
    submitButton.disabled = true;
    uploadStatus.className = "story-status";
    uploadStatus.textContent = "Uploading your story...";
    const body = new FormData();
    body.append("media", file);
    body.append("caption", captionInput.value.trim());
    if (selectedVideoData.duration !== "") body.append("duration", selectedVideoData.duration);
    if (selectedVideoData.thumbnail) body.append("thumbnail", selectedVideoData.thumbnail);
    try {
      await apiRequest("stories", { method: "POST", body });
      uploadForm.reset();
      preview.innerHTML = "";
      preview.hidden = true;
      selectedVideoData = { duration: "", thumbnail: "" };
      uploadStatus.className = "story-status story-success";
      uploadStatus.textContent = "Your story is live.";
      await loadStories();
    } catch (error) {
      uploadStatus.className = "story-status story-error";
      uploadStatus.textContent = error.message;
    } finally {
      submitButton.disabled = false;
    }
  });

  refreshButton?.addEventListener("click", loadStories);
  previousButton?.addEventListener("click", () => rail.scrollBy({ left: -rail.clientWidth, behavior: "smooth" }));
  nextButton?.addEventListener("click", () => rail.scrollBy({ left: rail.clientWidth, behavior: "smooth" }));
  rail.addEventListener("scroll", () => {
    previousButton.disabled = rail.scrollLeft <= 2;
    nextButton.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2;
  }, { passive: true });
  setPanel("posts");
  loadStories();
})();
