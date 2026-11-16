(() => {
  const searchInput = document.getElementById("busca_livro");
  const bookIdInput = document.getElementById("livro_id");
  const resultsList = document.getElementById("resultados_livros");
  const searchStatus = document.getElementById("status_busca_livro");
  const selectedBook = document.getElementById("livro_selecionado");
  const loanForm = searchInput?.form;
  const studentSearchInput = document.getElementById("busca_aluno");
  const studentIdInput = document.getElementById("aluno_id");
  const studentResultsList = document.getElementById("resultados_alunos");
  const studentSearchStatus = document.getElementById("status_busca_aluno");
  const selectedStudent = document.getElementById("aluno_selecionado");

  if (
    !searchInput ||
    !bookIdInput ||
    !resultsList ||
    !searchStatus ||
    !selectedBook ||
    !loanForm
  ) {
    return;
  }

  let debounceTimer;
  let activeRequest;

  const clearSelection = () => {
    bookIdInput.value = "";
    selectedBook.textContent = "";
    selectedBook.hidden = true;
    searchInput.removeAttribute("aria-invalid");
  };

  const showResults = (books) => {
    resultsList.replaceChildren();

    books.forEach((book) => {
      const item = document.createElement("li");
      item.className = "list-group-item p-0";

      const button = document.createElement("button");
      button.type = "button";
      button.className =
        "list-group-item list-group-item-action text-start border-0";

      const title = document.createElement("strong");
      title.className = "d-block";
      title.textContent = book.titulo;

      const details = document.createElement("span");
      details.className = "d-block small text-body-secondary";
      details.textContent =
        `${book.autor} · ISBN: ${book.isbn} · ` +
        `${book.disponiveis} exemplar(es) disponível(is)`;

      button.append(title, details);
      button.addEventListener("click", () => {
        bookIdInput.value = book.id;
        selectedBook.textContent = `Livro selecionado: ${book.titulo} — ${book.autor} (ISBN: ${book.isbn})`;
        selectedBook.hidden = false;
        resultsList.replaceChildren();
        searchStatus.textContent = "";
        searchInput.removeAttribute("aria-invalid");
      });

      item.append(button);
      resultsList.append(item);
    });
  };

  searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    activeRequest?.abort();
    clearSelection();
    resultsList.replaceChildren();

    const term = searchInput.value.trim();
    if (!term) {
      searchStatus.textContent = "";
      return;
    }

    searchStatus.textContent = "Pesquisando livros...";
    debounceTimer = setTimeout(async () => {
      const controller = new AbortController();
      activeRequest = controller;

      try {
        const url = new URL(
          searchInput.dataset.searchUrl,
          window.location.href,
        );
        url.searchParams.set("q", term);
        const response = await fetch(url, { signal: controller.signal });
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.erro || "Não foi possível pesquisar livros.");
        }

        if (searchInput.value.trim() !== term) return;

        showResults(data.livros);
        searchStatus.textContent =
          data.livros.length === 0
            ? "Nenhum livro disponível foi encontrado."
            : `${data.livros.length} resultado(s) encontrado(s).`;
      } catch (error) {
        if (error.name === "AbortError") return;
        searchStatus.textContent =
          error.message ||
          "Não foi possível pesquisar livros. Tente novamente.";
      }
    }, 250);
  });

  if (
    studentSearchInput &&
    studentIdInput &&
    studentResultsList &&
    studentSearchStatus &&
    selectedStudent
  ) {
    let studentDebounceTimer;
    let activeStudentRequest;

    const clearStudentSelection = () => {
      studentIdInput.value = "";
      selectedStudent.textContent = "";
      selectedStudent.hidden = true;
      studentSearchInput.removeAttribute("aria-invalid");
    };

    const showStudentResults = (students) => {
      studentResultsList.replaceChildren();

      students.forEach((student) => {
        const item = document.createElement("li");
        item.className = "list-group-item p-0";

        const button = document.createElement("button");
        button.type = "button";
        button.className =
          "list-group-item list-group-item-action text-start border-0";

        const name = document.createElement("strong");
        name.className = "d-block";
        name.textContent = student.nome;

        const details = document.createElement("span");
        details.className = "d-block small text-body-secondary";
        details.textContent = `Turma: ${student.turma}`;

        button.append(name, details);
        button.addEventListener("click", () => {
          studentIdInput.value = student.id;
          selectedStudent.textContent = `Aluno selecionado: ${student.nome} — Turma: ${student.turma}`;
          selectedStudent.hidden = false;
          studentResultsList.replaceChildren();
          studentSearchStatus.textContent = "";
          studentSearchInput.removeAttribute("aria-invalid");
        });

        item.append(button);
        studentResultsList.append(item);
      });
    };

    studentSearchInput.addEventListener("input", () => {
      clearTimeout(studentDebounceTimer);
      activeStudentRequest?.abort();
      clearStudentSelection();
      studentResultsList.replaceChildren();

      const term = studentSearchInput.value.trim();
      if (!term) {
        studentSearchStatus.textContent = "";
        return;
      }

      studentSearchStatus.textContent = "Pesquisando alunos...";
      studentDebounceTimer = setTimeout(async () => {
        const controller = new AbortController();
        activeStudentRequest = controller;

        try {
          const url = new URL(
            studentSearchInput.dataset.searchUrl,
            window.location.href,
          );
          url.searchParams.set("q", term);
          const response = await fetch(url, { signal: controller.signal });
          const data = await response.json();

          if (!response.ok) {
            throw new Error(data.erro || "Não foi possível pesquisar alunos.");
          }

          if (studentSearchInput.value.trim() !== term) return;

          showStudentResults(data.alunos);
          studentSearchStatus.textContent =
            data.alunos.length === 0
              ? "Nenhum aluno encontrado."
              : `${data.alunos.length} resultado(s) encontrado(s).`;
        } catch (error) {
          if (error.name === "AbortError") return;
          studentSearchStatus.textContent =
            error.message ||
            "Não foi possível pesquisar alunos. Tente novamente.";
        }
      }, 250);
    });
  }

  loanForm.addEventListener("submit", (event) => {
    if (studentIdInput && !studentIdInput.value) {
      event.preventDefault();
      studentSearchInput?.setAttribute("aria-invalid", "true");
      if (studentSearchStatus) {
        studentSearchStatus.textContent =
          "Pesquise e selecione um aluno cadastrado.";
      }
      studentSearchInput?.focus();
      return;
    }

    if (!bookIdInput.value) {
      event.preventDefault();
      searchInput.setAttribute("aria-invalid", "true");
      searchStatus.textContent = "Pesquise e selecione um livro disponível.";
      searchInput.focus();
    }
  });
})();
