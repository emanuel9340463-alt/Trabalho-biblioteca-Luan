document.querySelectorAll("[data-password-toggle]").forEach((button) => {
  const input = document.getElementById(button.getAttribute("aria-controls"));

  if (!input) return;

  button.addEventListener("click", () => {
    const mostrarSenha = input.type === "password";
    input.type = mostrarSenha ? "text" : "password";
    button.textContent = mostrarSenha ? "Ocultar" : "Mostrar";
    button.setAttribute(
      "aria-label",
      mostrarSenha ? "Ocultar senha" : "Mostrar senha",
    );
    button.setAttribute("aria-pressed", String(mostrarSenha));
  });
});
