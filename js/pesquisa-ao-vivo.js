document.querySelectorAll("form[data-live-search]").forEach((form) => {
  const input = form.querySelector('input[type="search"]');
  const results = document.querySelector(form.dataset.searchResults);
  if (!input || !results) return;

  let debounceTimer;
  let activeRequest;

  const search = async () => {
    const url = new URL(form.action, document.baseURI);
    const term = input.value.trim();

    if (term) {
      url.searchParams.set("q", term);
    } else {
      url.searchParams.delete("q");
    }

    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
      const response = await fetch(url, {
        signal: activeRequest.signal,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });

      if (!response.ok) {
        throw new Error("A pesquisa não pôde ser atualizada.");
      }

      const html = await response.text();
      const documentAtualizado = new DOMParser().parseFromString(
        html,
        "text/html",
      );
      const novosResultados = documentAtualizado.querySelector(
        form.dataset.searchResults,
      );

      if (!novosResultados) {
        throw new Error("A resposta da pesquisa está incompleta.");
      }

      results.innerHTML = novosResultados.innerHTML;
      applyStudentView();
      window.history.replaceState(null, "", url);
    } catch (error) {
      if (error.name === "AbortError") return;

      console.error(error);
      results.innerHTML =
        '<div class="alert alert-danger" role="alert">' +
        "Não foi possível atualizar a pesquisa. Tente novamente." +
        "</div>";
    }
  };

  const applyStudentView = () => {
    const selectedView =
      results.dataset.studentView ??
      results.querySelector("[data-student-view-control]:checked")?.value ??
      "grupos";

    results.dataset.studentView = selectedView;
    results.querySelectorAll("[data-student-view]").forEach((view) => {
      view.hidden = view.dataset.studentView !== selectedView;
    });
    results
      .querySelectorAll("[data-student-view-control]")
      .forEach((control) => {
        control.checked = control.value === selectedView;
      });
  };

  results.addEventListener("change", (event) => {
    if (event.target.matches("[data-student-view-control]")) {
      results.dataset.studentView = event.target.value;
      applyStudentView();
    }
  });
  applyStudentView();

  input.addEventListener("input", () => {
    window.clearTimeout(debounceTimer);
    activeRequest?.abort();
    debounceTimer = window.setTimeout(search, 300);
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    window.clearTimeout(debounceTimer);
    search();
  });
});
