(function () {
  "use strict";

  function rootUrl(relative) {
    if (typeof window.appUrl === "function") return window.appUrl(relative);
    const base = String(window.__APP_BASE__ || "").replace(/\/$/, "");
    return `${base}/${String(relative).replace(/^\//, "")}`;
  }

  function ratingRoute(type, id) {
    const section = { post: "posts", story: "stories", profile: "profiles" }[type];
    return rootUrl(`app/index.php?route=${section}%2F${Number(id)}%2Frating`);
  }

  function createWidget(options) {
    const host = options.host || document.createElement("div");
    const type = options.type;
    const id = Number(options.id);
    let average = Number(options.average || 0);
    let count = Number(options.count || 0);
    let myRating = options.myRating === null || options.myRating === undefined || options.myRating === ""
      ? null
      : Number(options.myRating);
    const enabled = options.enabled !== false && window.__CURRENT_USER_ID__ !== null && window.__CURRENT_USER_ID__ !== undefined;

    host.classList.add("community-rating-widget");
    host.innerHTML = "";
    const label = document.createElement("span");
    label.className = "community-rating-label";
    label.textContent = options.label || "Community rating";
    const stars = document.createElement("div");
    stars.className = "community-rating-stars";
    stars.setAttribute("role", "radiogroup");
    stars.setAttribute("aria-label", `Rate this ${type} from 1 to 5 stars`);
    const summary = document.createElement("span");
    summary.className = "community-rating-summary";
    const message = document.createElement("span");
    message.className = "community-rating-message";

    function selectedValue(previewValue) {
      if (previewValue) return previewValue;
      return myRating || Math.round(average);
    }

    function paint(previewValue) {
      const selected = selectedValue(previewValue);
      stars.querySelectorAll("button").forEach((button) => {
        const value = Number(button.dataset.rating);
        button.classList.toggle("is-active", value <= selected);
        button.setAttribute("aria-checked", myRating === value ? "true" : "false");
      });
      summary.textContent = count
        ? `${average.toFixed(1)} · ${count} ${count === 1 ? "rating" : "ratings"}`
        : "Not rated yet";
    }

    async function submitRating(value) {
      message.classList.remove("is-error");
      message.textContent = "Saving...";
      stars.querySelectorAll("button").forEach((button) => { button.disabled = true; });
      try {
        const response = await fetch(ratingRoute(type, id), {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ rating: value }),
        });
        const raw = await response.text();
        let data;
        try { data = JSON.parse(raw); }
        catch (_) { throw new Error("The rating server returned an invalid response."); }
        if (!response.ok || data.error || data.success === false) {
          throw new Error(data.error || data.message || "The rating could not be saved.");
        }
        average = Number(data.average_rating || 0);
        count = Number(data.rating_count || 0);
        myRating = Number(data.my_rating || value);
        message.textContent = `Your ${myRating}-star rating was saved.`;
        paint();
        if (typeof options.onRated === "function") options.onRated(data);
      } catch (error) {
        message.textContent = error.message;
        message.classList.add("is-error");
      } finally {
        stars.querySelectorAll("button").forEach((button) => { button.disabled = !enabled; });
      }
    }

    for (let value = 1; value <= 5; value += 1) {
      const button = document.createElement("button");
      button.type = "button";
      button.dataset.rating = value;
      button.textContent = "★";
      button.setAttribute("role", "radio");
      button.setAttribute("aria-label", `${value} ${value === 1 ? "star" : "stars"}`);
      button.disabled = !enabled;
      button.addEventListener("mouseenter", () => paint(value));
      button.addEventListener("focus", () => paint(value));
      button.addEventListener("click", () => submitRating(value));
      stars.appendChild(button);
    }
    stars.addEventListener("mouseleave", () => paint());
    stars.addEventListener("focusout", (event) => {
      if (!stars.contains(event.relatedTarget)) paint();
    });

    if (!enabled) message.textContent = "Sign in to rate.";
    host.append(label, stars, summary, message);
    paint();
    return host;
  }

  window.AAARatings = { createWidget };

  document.querySelectorAll("[data-rating-target]").forEach((host) => {
    createWidget({
      host,
      type: host.dataset.ratingTarget,
      id: host.dataset.ratingId,
      average: host.dataset.ratingAverage,
      count: host.dataset.ratingCount,
      myRating: host.dataset.myRating,
      enabled: host.dataset.ratingEnabled !== "0",
    });
  });
})();
