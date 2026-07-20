// JavaScript Document// Simple router for All America Atlantic
(function () {
  const params = new URLSearchParams(window.location.search);
  const page = params.get("page");

  switch (page) {
    case "splash":
      window.location.href = "splash.html";
      break;

    case "about":
      window.location.href = "about.html";
      break;

    case "app":
      window.location.href = "app.html";
      break;

    default:
      // stay on index
      break;
  }
})();
