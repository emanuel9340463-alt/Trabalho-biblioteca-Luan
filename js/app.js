(() => {
  const sidebar = document.getElementById("appSidebar");
  const collapseButton = document.querySelector("[data-sidebar-toggle]");
  const mobileButton = document.querySelector("[data-mobile-menu-toggle]");
  const backdrop = document.querySelector("[data-sidebar-backdrop]");

  if (!sidebar) return;

  const setCollapsed = (collapsed) => {
    document.body.classList.toggle("sidebar-collapsed", collapsed);
    collapseButton?.setAttribute("aria-expanded", String(!collapsed));
    collapseButton?.setAttribute(
      "aria-label",
      collapsed ? "Ampliar menu" : "Recolher menu",
    );
    if (collapseButton) {
      collapseButton.title = collapsed ? "Ampliar menu" : "Recolher menu";
    }
    try {
      localStorage.setItem("biblioteca-sidebar-collapsed", String(collapsed));
    } catch (error) {
      console.warn("Não foi possível salvar a preferência do menu.", error);
    }
  };

  try {
    setCollapsed(
      localStorage.getItem("biblioteca-sidebar-collapsed") === "true",
    );
  } catch (error) {
    console.warn("Não foi possível carregar a preferência do menu.", error);
  }

  collapseButton?.addEventListener("click", () => {
    setCollapsed(!document.body.classList.contains("sidebar-collapsed"));
  });

  const setMobileMenuOpen = (open) => {
    document.body.classList.toggle("mobile-menu-open", open);
    mobileButton?.setAttribute("aria-expanded", String(open));
  };

  mobileButton?.addEventListener("click", () => {
    setMobileMenuOpen(!document.body.classList.contains("mobile-menu-open"));
  });
  backdrop?.addEventListener("click", () => setMobileMenuOpen(false));
  sidebar.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => setMobileMenuOpen(false));
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setMobileMenuOpen(false);
  });
})();
