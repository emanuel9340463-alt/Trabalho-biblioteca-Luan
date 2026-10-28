const isbnInput = document.getElementById("isbn");
const capaPreview = document.getElementById("isbnCoverPreview");
const capaMensagem = document.getElementById("isbnCoverMessage");
let buscaCapaAtual = 0;

isbnInput.addEventListener("input", function () {
  const isbn = this.value.replace(/[^0-9Xx]/g, "");
  const buscaId = ++buscaCapaAtual;

  capaPreview.hidden = true;
  capaPreview.removeAttribute("src");

  if (isbn.length !== 10 && isbn.length !== 13) {
    capaMensagem.textContent = "A capa correspondente aparecerá aqui.";
    return;
  }

  capaMensagem.textContent = "Buscando capa pelo ISBN...";
  capaPreview.onload = function () {
    if (buscaId !== buscaCapaAtual) return;

    if (this.naturalWidth <= 1) {
      this.hidden = true;
      capaMensagem.textContent = "Não foi encontrada uma capa para este ISBN.";
      return;
    }

    this.hidden = false;
    capaMensagem.textContent = "Capa encontrada pelo ISBN.";
  };
  capaPreview.onerror = function () {
    if (buscaId !== buscaCapaAtual) return;
    this.hidden = true;
    capaMensagem.textContent = "Não foi encontrada uma capa para este ISBN.";
  };
  capaPreview.src = `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(isbn)}-M.jpg?default=false`;
});
