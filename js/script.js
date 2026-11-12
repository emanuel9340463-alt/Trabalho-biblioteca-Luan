const isbnInput = document.getElementById("isbn");
const tituloInput = document.getElementById("livro");
const autorInput = document.getElementById("autor");

const capaInput = document.getElementById("capa");
const capaPreview = document.getElementById("isbnCoverPreview");
const capaMensagem = document.getElementById("isbnCoverMessage");
const isbnLookupStatus = document.getElementById("isbnLookupStatus");
const livroForm = document.getElementById("livroForm");

let buscaCapaAtual = 0;
let urlPreviaManual = null;
let temporizadorBusca = null;
let temporizadorMetadados = null;
let controladorMetadados = null;
let buscaMetadadosAtual = 0;
let isbnMetadadosAtual = "";
const valoresPreenchidosAutomaticamente = {};

// =====================================================
// 1. EXIBIR PRÉVIA DA IMAGEM
// =====================================================

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
      if (buscaId === buscaCapaAtual) {
        capaPreview.hidden = true;
      }

      resolve(false);
    };

    capaPreview.hidden = true;
    capaPreview.src = url;
  });
}

// =====================================================
// 2. NORMALIZAR TEXTOS
// =====================================================

function normalizarTexto(texto) {
  return texto
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "");
}

function similaridadeTexto(texto1, texto2) {
  const a = normalizarTexto(texto1);
  const b = normalizarTexto(texto2);

  if (!a || !b) return 0;

  let matriz = Array.from({ length: a.length + 1 }, () =>
    Array(b.length + 1).fill(0),
  );

  for (let i = 0; i <= a.length; i++) matriz[i][0] = i;
  for (let j = 0; j <= b.length; j++) matriz[0][j] = j;

  for (let i = 1; i <= a.length; i++) {
    for (let j = 1; j <= b.length; j++) {
      const custo = a[i - 1] === b[j - 1] ? 0 : 1;

      matriz[i][j] = Math.min(
        matriz[i - 1][j] + 1,
        matriz[i][j - 1] + 1,
        matriz[i - 1][j - 1] + custo,
      );
    }
  }

  const distancia = matriz[a.length][b.length];

  return (1 - distancia / Math.max(a.length, b.length)) * 100;
}

function correspondeLivro(tituloBuscado, autorBuscado, livro) {
  const tituloEncontrado = livro.title || "";
  const autoresEncontrados = livro.authors || livro.author_name || [];

  if (!tituloBuscado || !autorBuscado || !tituloEncontrado) {
    return false;
  }

  if (similaridadeTexto(tituloBuscado, tituloEncontrado) < 80) {
    return false;
  }

  return autoresEncontrados.some(
    (autor) => similaridadeTexto(autorBuscado, autor) >= 75,
  );
}

// =====================================================
// 3. PREENCHER OS DADOS DO LIVRO PELO ISBN
// =====================================================

async function buscarMetadadosGoogle(isbn, signal) {
  const url = `https://www.googleapis.com/books/v1/volumes?q=isbn:${encodeURIComponent(isbn)}&maxResults=1`;
  const resposta = await fetch(url, { signal });

  if (!resposta.ok) return null;

  const dados = await resposta.json();
  const info = dados.items?.[0]?.volumeInfo;

  if (!info?.title) return null;

  return {
    titulo: info.title.slice(0, 200),
    autor: Array.isArray(info.authors)
      ? info.authors.join(", ").slice(0, 100)
      : "",
    genero: Array.isArray(info.categories)
      ? (info.categories[0] || "").slice(0, 50)
      : "",
  };
}

async function buscarMetadadosOpenLibrary(isbn, signal) {
  const pesquisa = new URLSearchParams({
    isbn,
    limit: "1",
    fields: "title,author_name,subject",
  });
  const resposta = await fetch(
    `https://openlibrary.org/search.json?${pesquisa}`,
    { signal },
  );

  if (!resposta.ok) return null;

  const dados = await resposta.json();
  const livro = dados.docs?.[0];

  if (!livro?.title) return null;

  return {
    titulo: livro.title.slice(0, 200),
    autor: Array.isArray(livro.author_name)
      ? livro.author_name.join(", ").slice(0, 100)
      : "",
    genero: Array.isArray(livro.subject)
      ? (livro.subject[0] || "").slice(0, 50)
      : "",
  };
}

function preencherSeNaoEditado(campo, valor) {
  if (!valor) return;

  const anterior = valoresPreenchidosAutomaticamente[campo];
  const input = {
    titulo: tituloInput,
    autor: autorInput,
    genero: document.getElementById("genero"),
  }[campo];

  if (!input) return;

  if (!valor) {
    if (input.value === anterior) input.value = "";
    delete valoresPreenchidosAutomaticamente[campo];
    return;
  }

  if (!input.value.trim() || input.value === anterior) {
    input.value = valor;
    valoresPreenchidosAutomaticamente[campo] = valor;
  }
}

async function pesquisarMetadados() {
  const isbn = isbnInput.value.replace(/[^0-9Xx]/g, "").toUpperCase();
  const buscaId = ++buscaMetadadosAtual;

  controladorMetadados?.abort();

  if (isbn.length !== 10 && isbn.length !== 13) {
    isbnLookupStatus.textContent =
      "Ao informar o ISBN, vamos procurar os dados do livro.";
    isbnLookupStatus.className = "isbn-lookup-status mt-2";
    return;
  }

  controladorMetadados = new AbortController();
  const controlador = controladorMetadados;
  const temporizadorLimite = setTimeout(() => controlador.abort(), 7000);

  isbnLookupStatus.textContent = "Buscando os dados do livro...";
  isbnLookupStatus.className = "isbn-lookup-status mt-2";

  try {
    const resultados = await Promise.allSettled([
      buscarMetadadosGoogle(isbn, controlador.signal),
      buscarMetadadosOpenLibrary(isbn, controlador.signal),
    ]);

    if (buscaId !== buscaMetadadosAtual) return;
    const candidatos = resultados
      .filter((resultado) => resultado.status === "fulfilled")
      .map((resultado) => resultado.value)
      .filter(Boolean);
    const metadados = candidatos.sort(
      (a, b) =>
        Number(Boolean(b.autor)) * 2 +
        Number(Boolean(b.genero)) -
        (Number(Boolean(a.autor)) * 2 + Number(Boolean(a.genero))),
    )[0];

    if (!metadados) {
      isbnLookupStatus.textContent =
        "Não encontramos este ISBN. Confira o número ou preencha manualmente.";
      isbnLookupStatus.className = "isbn-lookup-status is-error mt-2";
      return;
    }

    const adicionais = candidatos.reduce(
      (resultado, candidato) => ({
        autor: resultado.autor || candidato.autor,
        genero: resultado.genero || candidato.genero,
      }),
      { autor: "", genero: "" },
    );

    preencherSeNaoEditado("titulo", metadados.titulo);
    preencherSeNaoEditado("autor", metadados.autor || adicionais.autor);
    preencherSeNaoEditado("genero", metadados.genero || adicionais.genero);
    isbnLookupStatus.textContent =
      "Dados encontrados. Confira as informações antes de cadastrar.";
    isbnLookupStatus.className = "isbn-lookup-status is-success mt-2";
    agendarBusca();
  } catch (erro) {
    if (buscaId !== buscaMetadadosAtual) return;

    isbnLookupStatus.textContent =
      erro.name === "AbortError"
        ? "A busca demorou mais que o esperado. Você pode preencher os dados manualmente."
        : "Não encontramos este ISBN. Confira o número ou preencha manualmente.";
    isbnLookupStatus.className = "isbn-lookup-status is-error mt-2";
  } finally {
    clearTimeout(temporizadorLimite);
  }
}

function agendarBuscaMetadados() {
  const isbn = isbnInput.value.replace(/[^0-9Xx]/g, "").toUpperCase();

  if (isbn !== isbnMetadadosAtual) {
    Object.entries(valoresPreenchidosAutomaticamente).forEach(
      ([campo, valor]) => {
        const input = {
          titulo: tituloInput,
          autor: autorInput,
          genero: document.getElementById("genero"),
        }[campo];

        if (input?.value === valor) input.value = "";
        delete valoresPreenchidosAutomaticamente[campo];
      },
    );
    isbnMetadadosAtual = isbn;
  }

  clearTimeout(temporizadorMetadados);
  controladorMetadados?.abort();
  temporizadorMetadados = setTimeout(pesquisarMetadados, 450);
}

// =====================================================
// 4. BUSCA NO GOOGLE BOOKS
// =====================================================

async function buscarCapaGoogle(isbn, buscaId) {
  const url = `https://www.googleapis.com/books/v1/volumes?q=isbn:${encodeURIComponent(isbn)}&maxResults=5`;

  const resposta = await fetch(url);

  if (!resposta.ok) return false;

  const dados = await resposta.json();

  for (const livro of dados.items || []) {
    const capas = livro.volumeInfo?.imageLinks || {};

    for (const tipo of ["thumbnail", "smallThumbnail"]) {
      const urlCapa = capas[tipo]?.replace(/^http:/i, "https:");

      if (!urlCapa) continue;

      const host = new URL(urlCapa).hostname;

      if (!["books.google.com", "books.googleusercontent.com"].includes(host)) {
        continue;
      }

      if (await carregarPrevia(urlCapa, buscaId)) {
        return true;
      }
    }
  }

  return false;
}

// =====================================================
// 5. BUSCA NO GOOGLE BOOKS POR TÍTULO E AUTOR
// =====================================================

async function buscarCapaGoogleTitulo(titulo, autor, buscaId) {
  const pesquisa = new URLSearchParams({
    q: `intitle:${titulo} inauthor:${autor}`,
    maxResults: "10",
  });

  const resposta = await fetch(
    `https://www.googleapis.com/books/v1/volumes?${pesquisa}`,
  );

  if (!resposta.ok) return false;

  const dados = await resposta.json();

  for (const livro of dados.items || []) {
    if (!correspondeLivro(titulo, autor, livro.volumeInfo || {})) {
      continue;
    }

    const capas = livro.volumeInfo?.imageLinks || {};

    for (const tipo of ["thumbnail", "smallThumbnail"]) {
      const urlCapa = capas[tipo]?.replace(/^http:/i, "https:");

      if (!urlCapa) continue;

      const host = new URL(urlCapa).hostname;

      if (!["books.google.com", "books.googleusercontent.com"].includes(host)) {
        continue;
      }

      if (await carregarPrevia(urlCapa, buscaId)) {
        return true;
      }
    }
  }

  return false;
}

// =====================================================
// 6. BUSCA NO OPEN LIBRARY POR TÍTULO E AUTOR
// =====================================================

async function buscarCapaOpenLibraryTitulo(titulo, autor, buscaId) {
  const pesquisa = new URLSearchParams({
    title: titulo,
    author: autor,
    limit: "10",
    fields: "title,author_name,cover_i",
  });

  const resposta = await fetch(
    `https://openlibrary.org/search.json?${pesquisa}`,
  );

  if (!resposta.ok) return false;

  const dados = await resposta.json();

  for (const livro of dados.docs || []) {
    if (!livro.cover_i) continue;

    if (!correspondeLivro(titulo, autor, livro)) {
      continue;
    }

    const urlCapa = `https://covers.openlibrary.org/b/id/${livro.cover_i}-M.jpg?default=false`;

    if (await carregarPrevia(urlCapa, buscaId)) {
      return true;
    }
  }

  return false;
}

// =====================================================
// 7. ORGANIZAR A PESQUISA
// =====================================================

async function pesquisarCapa() {
  const isbn = isbnInput.value.replace(/[^0-9Xx]/g, "");
  const titulo = tituloInput.value.trim();
  const autor = autorInput.value.trim();

  const buscaId = ++buscaCapaAtual;

  if (capaInput.files.length > 0) return;

  capaPreview.hidden = true;
  capaPreview.removeAttribute("src");

  if (isbn.length !== 10 && isbn.length !== 13) {
    capaMensagem.textContent = "A capa correspondente aparecerá aqui.";
    return;
  }

  capaMensagem.textContent = "Buscando capa do livro...";

  try {
    // 1. Open Library por ISBN
    let encontrada = await carregarPrevia(
      `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(isbn)}-M.jpg?default=false`,
      buscaId,
    );

    if (buscaId !== buscaCapaAtual) return;

    // 2. Google Books por ISBN
    if (!encontrada) {
      encontrada = await buscarCapaGoogle(isbn, buscaId);
    }

    if (buscaId !== buscaCapaAtual) return;

    // 3. Google Books por título e autor
    if (!encontrada && titulo && autor) {
      encontrada = await buscarCapaGoogleTitulo(titulo, autor, buscaId);
    }

    if (buscaId !== buscaCapaAtual) return;

    // 4. Open Library por título e autor
    if (!encontrada && titulo && autor) {
      encontrada = await buscarCapaOpenLibraryTitulo(titulo, autor, buscaId);
    }

    if (buscaId !== buscaCapaAtual) return;

    capaMensagem.textContent = encontrada
      ? "Capa encontrada automaticamente!"
      : "Não encontramos uma capa. Você pode enviar uma imagem manualmente.";
  } catch (erro) {
    if (buscaId === buscaCapaAtual) {
      capaPreview.hidden = true;

      capaMensagem.textContent =
        "Não foi possível buscar a capa. Você pode enviar uma imagem manualmente.";
    }
  }
}

// =====================================================
// 8. EVENTOS DOS CAMPOS
// =====================================================

function agendarBusca() {
  clearTimeout(temporizadorBusca);

  if (capaInput.files.length > 0) return;

  // Invalida pesquisas anteriores imediatamente
  buscaCapaAtual++;

  temporizadorBusca = setTimeout(() => {
    pesquisarCapa();
  }, 600);
}

isbnInput.addEventListener("input", agendarBusca);
isbnInput.addEventListener("input", agendarBuscaMetadados);
tituloInput.addEventListener("input", agendarBusca);
autorInput.addEventListener("input", agendarBusca);
document.getElementById("genero").addEventListener("input", agendarBusca);

// =====================================================
// 9. UPLOAD MANUAL
// =====================================================

capaInput.addEventListener("change", async function () {
  clearTimeout(temporizadorBusca);

  const buscaId = ++buscaCapaAtual;

  if (urlPreviaManual) {
    URL.revokeObjectURL(urlPreviaManual);
    urlPreviaManual = null;
  }

  const arquivo = this.files[0];

  if (!arquivo) {
    pesquisarCapa();
    return;
  }

  const formatosPermitidos = ["image/jpeg", "image/png", "image/webp"];

  if (
    !formatosPermitidos.includes(arquivo.type) ||
    arquivo.size > 5 * 1024 * 1024
  ) {
    this.value = "";
    capaPreview.hidden = true;

    capaMensagem.textContent =
      "Selecione uma imagem JPG, PNG ou WEBP de até 5 MB.";

    return;
  }

  urlPreviaManual = URL.createObjectURL(arquivo);

  const encontrada = await carregarPrevia(urlPreviaManual, buscaId);

  if (buscaId === buscaCapaAtual) {
    capaMensagem.textContent = encontrada
      ? "Prévia da capa enviada manualmente."
      : "Não foi possível exibir a imagem selecionada.";
  }
});

async function salvarCapaPendente() {
  if (livroForm.dataset.capaPendente !== "true") return;

  livroForm.dataset.capaPendente = "false";
  const mensagem = document.getElementById("mensagem");
  const dados = new URLSearchParams({
    isbn: livroForm.dataset.capaIsbn || "",
    titulo: livroForm.dataset.capaTitulo || "",
    autor: livroForm.dataset.capaAutor || "",
  });

  try {
    const resposta = await fetch("cadastrolivro.php?acao=buscar-capa", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: dados,
      keepalive: true,
    });

    if (!resposta.ok) {
      throw new Error("Não foi possível concluir a busca da capa.");
    }

    const resultado = await resposta.json();
    const aviso = document.createElement("div");
    aviso.className = `alert ${resultado.encontrada ? "alert-success" : "alert-warning"}`;
    aviso.setAttribute("role", "status");
    aviso.textContent = resultado.encontrada
      ? "Capa salva e disponível no acervo."
      : "Livro cadastrado. Não encontramos uma capa; você ainda pode enviá-la manualmente.";
    mensagem.replaceChildren(aviso);
  } catch (erro) {
    console.error("Não foi possível buscar a capa em segundo plano.", erro);
    const aviso = document.createElement("div");
    aviso.className = "alert alert-warning";
    aviso.setAttribute("role", "status");
    aviso.textContent =
      "Livro cadastrado. A busca automática de capa não foi concluída; você pode tentar novamente ou enviar uma imagem.";
    mensagem.replaceChildren(aviso);
  }
}

void salvarCapaPendente();
