/**
 * Thin wrapper over the JSON API at app/index.php.
 *
 * Every call goes through here so the base path, error handling and session
 * cookie behaviour stay consistent. The site may be installed at a domain root
 * or in a subdirectory, so paths are always built from window.__APP_BASE__
 * rather than assuming "/".
 */
(function (global) {
  "use strict";

  const base = (global.__APP_BASE__ || "").replace(/\/$/, "");
  const endpoint = base + "/app/index.php";

  /** Turns a stored relative media path into a URL this page can load. */
  function assetUrl(path) {
    if (!path) return "";
    if (/^(https?:)?\/\//i.test(path)) return path;
    return base + "/" + String(path).replace(/^\/+/, "");
  }

  function buildUrl(route, query) {
    let url = endpoint + "?route=" + route;
    if (query) {
      Object.keys(query).forEach(function (k) {
        if (query[k] === undefined || query[k] === null) return;
        url += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(query[k]);
      });
    }
    return url;
  }

  async function request(route, options) {
    options = options || {};

    const init = {
      method: options.method || "GET",
      // Send the session cookie. Same-origin is the default, but being
      // explicit avoids surprises if this is ever called cross-origin.
      credentials: "same-origin",
      headers: {},
    };

    if (options.formData) {
      init.body = options.formData; // browser sets the multipart boundary
    } else if (options.body !== undefined) {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(options.body);
    }

    let res;
    try {
      res = await fetch(buildUrl(route, options.query), init);
    } catch (networkError) {
      throw new ApiError("Network error — check your connection.", 0);
    }

    const text = await res.text();
    let data;
    try {
      data = text ? JSON.parse(text) : {};
    } catch (parseError) {
      // Surfaces PHP warnings or HTML error pages leaking into the body,
      // which is exactly the class of bug that used to break every endpoint.
      throw new ApiError("Server returned a non-JSON response.", res.status, text.slice(0, 300));
    }

    if (!res.ok || data.error) {
      throw new ApiError(data.error || "Request failed", res.status, data.details);
    }

    return data;
  }

  class ApiError extends Error {
    constructor(message, status, details) {
      super(message);
      this.name = "ApiError";
      this.status = status;
      this.details = details;
    }
  }

  global.Api = {
    assetUrl: assetUrl,
    error: ApiError,
    get: (route, query) => request(route, { method: "GET", query: query }),
    post: (route, body) => request(route, { method: "POST", body: body }),
    put: (route, body) => request(route, { method: "PUT", body: body }),
    del: (route, body) => request(route, { method: "DELETE", body: body }),
    upload: (route, formData) => request(route, { method: "POST", formData: formData }),

    /** Escapes text before it goes anywhere near innerHTML. */
    escape(value) {
      return String(value === null || value === undefined ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },

    /** "3h ago" style relative timestamps. */
    timeAgo(value) {
      if (!value) return "";
      const then = new Date(String(value).replace(" ", "T"));
      if (isNaN(then)) return "";
      const secs = Math.floor((Date.now() - then.getTime()) / 1000);
      if (secs < 60) return "just now";
      const mins = Math.floor(secs / 60);
      if (mins < 60) return mins + "m ago";
      const hours = Math.floor(mins / 60);
      if (hours < 24) return hours + "h ago";
      const days = Math.floor(hours / 24);
      if (days < 7) return days + "d ago";
      return then.toLocaleDateString();
    },
  };
})(window);
