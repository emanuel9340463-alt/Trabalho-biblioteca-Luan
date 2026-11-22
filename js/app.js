(() => {
  // Localiza os controles usados para recolher e abrir o menu lateral.
  const sidebar = document.getElementById("appSidebar");
  const collapseButton = document.querySelector("[data-sidebar-toggle]");
  const mobileButton = document.querySelector("[data-mobile-menu-toggle]");
  const backdrop = document.querySelector("[data-sidebar-backdrop]");
  const submenuButton = sidebar?.querySelector("[data-submenu-toggle]");
  const submenu = submenuButton
    ? document.getElementById(submenuButton.getAttribute("aria-controls"))
    : null;

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

  if (submenuButton && submenu) {
    const activePage = submenu.querySelector('[aria-current="page"]');
    const desktopCollapsed =
      window.matchMedia("(min-width: 768px)").matches &&
      document.body.classList.contains("sidebar-collapsed");
    const shouldOpen = Boolean(activePage) && !desktopCollapsed;
    submenu.hidden = !shouldOpen;
    submenuButton.setAttribute("aria-expanded", String(shouldOpen));

    submenuButton.addEventListener("click", () => {
      const desktop = window.matchMedia("(min-width: 768px)").matches;
      const collapsed =
        desktop && document.body.classList.contains("sidebar-collapsed");

      if (collapsed) {
        setCollapsed(false);
        submenu.hidden = false;
        submenuButton.setAttribute("aria-expanded", "true");
        return;
      }

      const open = submenuButton.getAttribute("aria-expanded") !== "true";
      submenu.hidden = !open;
      submenuButton.setAttribute("aria-expanded", String(open));
    });
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
