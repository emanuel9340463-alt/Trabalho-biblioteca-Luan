(() => {
  const classForm = document.querySelector("[data-auto-submit]");
  const classSelect = classForm?.querySelector('select[name="turma_origem"]');

  classSelect?.addEventListener("change", () => {
    classForm.requestSubmit();
  });

  // O formulário só será tratado se estiver presente nesta página.
  const form = document.querySelector("[data-promotion-form]");

  if (!form) return;

  const checkboxes = Array.from(
    form.querySelectorAll("[data-student-checkbox]"),
  );
  const selectedCount = document.getElementById("promotionSelectedCount");
  const destination = document.getElementById("turma-destino");
  const errorMessage = form.querySelector("[data-promotion-error]");

  const updateCount = () => {
    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    selectedCount.textContent = `${selected} selecionado${selected === 1 ? "" : "s"}`;
  };

  form.querySelector("[data-select-all]")?.addEventListener("click", () => {
    checkboxes.forEach((checkbox) => {
      checkbox.checked = true;
    });
    updateCount();
  });

  form.querySelector("[data-deselect-all]")?.addEventListener("click", () => {
    checkboxes.forEach((checkbox) => {
      checkbox.checked = false;
    });
    updateCount();
  });

  checkboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", updateCount);
  });
  updateCount();

  form.addEventListener("submit", (event) => {
    errorMessage.hidden = true;
    errorMessage.textContent = "";

    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    if (selected === 0) {
      event.preventDefault();
      errorMessage.textContent = "Selecione ao menos um aluno para promover.";
      errorMessage.className = "alert alert-warning mt-3";
      errorMessage.hidden = false;
      checkboxes[0]?.focus();
      return;
    }

    const turmaOrigem = form.dataset.turmaOrigem.trim();
    const turmaDestino = destination.value.trim();
    if (
      turmaOrigem.toLocaleLowerCase("pt-BR") ===
      turmaDestino.toLocaleLowerCase("pt-BR")
    ) {
      event.preventDefault();
      errorMessage.textContent =
        "A turma de origem e a turma de destino precisam ser diferentes.";
      errorMessage.className = "alert alert-warning mt-3";
      errorMessage.hidden = false;
      destination.focus();
      return;
    }

    const confirmacao =
      `Promover ${selected} aluno${selected === 1 ? "" : "s"} da turma ` +
      `"${turmaOrigem}" para "${turmaDestino}"?`;
    if (!window.confirm(confirmacao)) {
      event.preventDefault();
    }
  });
})();
