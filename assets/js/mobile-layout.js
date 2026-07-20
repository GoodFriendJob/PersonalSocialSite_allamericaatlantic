(function () {
  "use strict";

  const mobileQuery = window.matchMedia("(max-width: 760px)");
  const profileDrawer = document.getElementById("mobile-profile-drawer");
  const networkDrawer = document.getElementById("mobile-network-drawer");
  const backdrop = document.querySelector(".mobile-drawer-backdrop");
  const panelButtons = document.querySelectorAll("[data-mobile-panel]");
  const closeButtons = document.querySelectorAll("[data-mobile-close]");

  if (!profileDrawer || !networkDrawer || !backdrop) return;

  function drawerFor(name) {
    if (name === "profile") return profileDrawer;
    if (name === "network") return networkDrawer;
    return null;
  }

  function setButtonState(openPanel) {
    panelButtons.forEach((button) => {
      button.setAttribute("aria-expanded", button.dataset.mobilePanel === openPanel ? "true" : "false");
    });
  }

  function closeDrawers() {
    profileDrawer.classList.remove("is-mobile-open");
    networkDrawer.classList.remove("is-mobile-open");
    backdrop.hidden = true;
    document.body.classList.remove("mobile-drawer-open");
    setButtonState("");
  }

  function openDrawer(name) {
    if (!mobileQuery.matches) return;
    const drawer = drawerFor(name);
    if (!drawer) return;

    const wasOpen = drawer.classList.contains("is-mobile-open");
    closeDrawers();
    if (wasOpen) return;

    drawer.classList.add("is-mobile-open");
    backdrop.hidden = false;
    document.body.classList.add("mobile-drawer-open");
    setButtonState(name);
    drawer.querySelector("[data-mobile-close]")?.focus({ preventScroll: true });
  }

  panelButtons.forEach((button) => {
    button.addEventListener("click", () => openDrawer(button.dataset.mobilePanel || ""));
  });
  closeButtons.forEach((button) => button.addEventListener("click", closeDrawers));
  backdrop.addEventListener("click", closeDrawers);
  document.getElementById("btn-edit-profile")?.addEventListener("click", closeDrawers);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeDrawers();
  });

  const resetForViewport = () => {
    if (!mobileQuery.matches) closeDrawers();
  };
  if (typeof mobileQuery.addEventListener === "function") {
    mobileQuery.addEventListener("change", resetForViewport);
  } else {
    mobileQuery.addListener(resetForViewport);
  }
})();
