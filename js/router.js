// =========================================================
// SIMPLE SPA ROUTER FOR ALL AMERICAN NETWORK
// =========================================================

// Show a specific view by ID
function showView(viewName) {
  document.querySelectorAll(".view").forEach(v => v.classList.remove("active"));
  const view = document.getElementById(`view-${viewName}`);
  if (view) view.classList.add("active");
}

// Navigate to a view and update URL
function navigate(viewName) {
  history.pushState({ view: viewName }, "", `?view=${viewName}`);
  showView(viewName);
}

// Handle browser back/forward
window.addEventListener("popstate", (e) => {
  const view = e.state?.view || "general";
  showView(view);
});

// Load correct view on page load
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);
  const view = params.get("view") || "general";
  showView(view);
});
