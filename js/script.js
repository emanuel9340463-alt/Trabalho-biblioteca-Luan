const isbnInput = document.getElementById("isbn");
const capaInput = document.getElementById("capa");
const capaPreview = document.getElementById("isbnCoverPreview");
const capaMensagem = document.getElementById("isbnCoverMessage");
let buscaCapaAtual = 0;
let urlPreviaManual = null;

function carregarPrevia(url, buscaId) {
  return new Promise((resolve) => {
    capaPreview.onload = function () {
      if (buscaId !== buscaCapaAtual) {
        resolve(false);
        return;
      }

      const encontrada = this.naturalWidth > 1;
      this.hidden = !encontrada;
      resolve(encontrada);
    };
    capaPreview.onerror = function () {
      if (buscaId === buscaCapaAtual) capaPreview.hidden = true;
      resolve(false);
    };
    capaPreview.hidden = true;
    capaPreview.src = url;
  });
}

async function buscarCapaGoogle(isbn, buscaId) {
  const resposta = await fetch(
    `https://www.googleapis.com/books/v1/volumes?q=isbn:${encodeURIComponent(isbn)}&maxResults=5`,
  );
  if (!resposta.ok) return false;

  const dados = await resposta.json();
  for (const livro of dados.items || []) {
    const capas = livro.volumeInfo?.imageLinks || {};
    for (const tipo of ["thumbnail", "smallThumbnail"]) {
      const url = capas[tipo]?.replace(/^http:/i, "https:");
      if (url && (await carregarPrevia(url, buscaId))) return true;
    }
  }
  return false;
}

isbnInput.addEventListener("input", async function () {
  const isbn = this.value.replace(/[^0-9Xx]/g, "");
  const buscaId = ++buscaCapaAtual;

  if (capaInput.files.length > 0) return;

  capaPreview.hidden = true;
  capaPreview.removeAttribute("src");

  if (isbn.length !== 10 && isbn.length !== 13) {
    capaMensagem.textContent = "A capa correspondente aparecerá aqui.";
    return;
  }

  capaMensagem.textContent = "Buscando capa pelo ISBN...";
  try {
    let encontrada = await carregarPrevia(
      `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(isbn)}-M.jpg?default=false`,
      buscaId,
    );
    if (!encontrada && buscaId === buscaCapaAtual) {
      encontrada = await buscarCapaGoogle(isbn, buscaId);
    }

    if (buscaId === buscaCapaAtual) {
      capaMensagem.textContent = encontrada
        ? "Capa encontrada pelo ISBN."
        : "Não foi encontrada uma capa. Você pode enviar uma imagem manualmente.";
    }
  } catch {
    if (buscaId === buscaCapaAtual) {
      capaPreview.hidden = true;
      capaMensagem.textContent =
        "Não foi possível buscar a capa. Você pode enviar uma imagem manualmente.";
    }
  }
});

capaInput.addEventListener("change", async function () {
  const buscaId = ++buscaCapaAtual;
  if (urlPreviaManual) URL.revokeObjectURL(urlPreviaManual);

  const arquivo = this.files[0];
  if (!arquivo) {
    isbnInput.dispatchEvent(new Event("input"));
    return;
  }

  urlPreviaManual = URL.createObjectURL(arquivo);
  capaMensagem.textContent = (await carregarPrevia(urlPreviaManual, buscaId))
    ? "Prévia da capa enviada manualmente."
    : "Não foi possível exibir a imagem selecionada.";
});
