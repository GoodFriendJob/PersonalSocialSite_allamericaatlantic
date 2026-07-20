const revealNodes = document.querySelectorAll(".splash-kicker, .splash-title, .splash-subtitle, .splash-list li, .splash-actions .btn");

revealNodes.forEach((node, idx) => {
  node.classList.add("reveal");
  node.style.transitionDelay = `${Math.min(idx * 80, 640)}ms`;
});

requestAnimationFrame(() => {
  revealNodes.forEach((node) => node.classList.add("in"));
});
