(() => {
  const openButton = document.querySelector("[data-open-access-qr]");
  const dialog = document.getElementById("accessQrDialog");
  const closeButton = dialog?.querySelector("[data-close-access-qr]");
  const qrContainer = document.getElementById("accessQrCode");
  const qrError = document.getElementById("accessQrError");
  const qrLink = document.getElementById("accessQrLink");
  const localhostWarning = document.getElementById("accessQrLocalhostWarning");

  if (
    !openButton ||
    !dialog ||
    !closeButton ||
    !qrContainer ||
    !qrError ||
    !qrLink ||
    !localhostWarning
  ) {
    return;
  }

  const loginUrl = new URL("login.php", window.location.href);
  qrLink.href = loginUrl.href;
  qrLink.textContent = loginUrl.href;
  localhostWarning.hidden = !["localhost", "127.0.0.1", "[::1]"].includes(
    loginUrl.hostname,
  );

  openButton.addEventListener("click", () => {
    qrContainer.replaceChildren();
    qrError.hidden = true;

    const Writer = window.ZXingBrowser?.BrowserQRCodeSvgWriter;
    if (!Writer) {
      qrError.textContent =
        "Não foi possível carregar o gerador do código QR. Recarregue a página e tente novamente.";
      qrError.hidden = false;
    } else {
      try {
        new Writer().writeToDom(qrContainer, loginUrl.href, 272, 272);
        const svg = qrContainer.querySelector("svg");
        svg.setAttribute("role", "img");
        svg.setAttribute("aria-label", "Código QR para abrir a tela de login");
      } catch (error) {
        console.error("Não foi possível gerar o código QR.", error);
        qrError.textContent =
          "Não foi possível gerar o código QR para este endereço.";
        qrError.hidden = false;
      }
    }

    dialog.showModal();
  });

  closeButton.addEventListener("click", () => dialog.close());
})();
