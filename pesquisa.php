<?php
require_once 'config/conexao.php';

function buscarAlunosEmprestimo($conn, $livroId)
{
    $sql = "SELECT
                alunos.nome,
                alunos.turma,
                emprestimos.data_emprestimo,
                emprestimos.data_devolucao
            FROM emprestimos
            INNER JOIN alunos
                ON emprestimos.aluno_id = alunos.id
            WHERE emprestimos.livro_id = ?
              AND emprestimos.data_devolucao_real IS NULL";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $livroId);

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();
    $alunos = $resultado->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $alunos;
}

$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$livros = [];
$erro = '';

if ($termo === '') {
    $sql = "SELECT
            livros.id,
            livros.titulo,
            livros.autor,
            livros.genero,
            livros.isbn,
            livros.codigo_bibli,
            livros.quantidade,
            GREATEST(
    0,
    CAST(livros.quantidade AS SIGNED) - (
        SELECT COUNT(*)
        FROM emprestimos
        WHERE emprestimos.livro_id = livros.id
          AND emprestimos.data_devolucao_real IS NULL
    )
) AS disponiveis
        FROM livros
        ORDER BY livros.titulo";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT
            livros.id,
            livros.titulo,
            livros.autor,
            livros.genero,
            livros.isbn,
            livros.codigo_bibli,
            livros.quantidade,
            GREATEST(
    0,
    CAST(livros.quantidade AS SIGNED) - (
        SELECT COUNT(*)
        FROM emprestimos
        WHERE emprestimos.livro_id = livros.id
          AND emprestimos.data_devolucao_real IS NULL
    )
) AS disponiveis
        FROM livros
        WHERE livros.titulo LIKE CONCAT('%', ?, '%')
          OR livros.autor LIKE CONCAT('%', ?, '%')
          OR livros.isbn LIKE CONCAT('%', ?, '%')
          OR livros.codigo_bibli LIKE CONCAT('%', ?, '%')
        ORDER BY livros.titulo";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('ssss', $termo, $termo, $termo, $termo);
    }
}

if (!$stmt) {
    $erro = 'Não foi possível preparar a pesquisa. Tente novamente mais tarde.';
} elseif (!$stmt->execute()) {
    $erro = 'Não foi possível carregar os livros. Tente novamente mais tarde.';
} else {
    $resultado = $stmt->get_result();
    while ($livro = $resultado->fetch_assoc()) {
        $livros[] = $livro;
    }
}

if (isset($stmt) && $stmt) {
    $stmt->close();
}
$conn->close();

function escapar($valor)
{
  return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function livroDisponivel($status)
{
    $status = strtolower(trim((string) $status));
    $status = strtr($status, ['í' => 'i', 'Í' => 'i']);
    return in_array($status, ['disponivel', 'available'], true);
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesquisa de livros | Biblioteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/pesquisa.css">
  </head>
  <body>
    <main class="container py-5">
      <header class="page-header mb-4">
        <p class="eyebrow mb-2">BIBLIOTECA</p>
        <h1>Encontre seu próximo livro</h1>
        <p class="text-secondary mb-0">Pesquise por título, autor, ISBN ou código da biblioteca.</p>
      </header>

      <form class="search-form mb-5" action="pesquisa.php" method="get" role="search">
        <label class="visually-hidden" for="termo-pesquisa">Título, autor, ISBN ou código da biblioteca</label>
        <input
          class="form-control"
          id="termo-pesquisa"
          name="q"
          type="search"
          placeholder="Título, autor, ISBN ou código da biblioteca"
          value="<?= escapar($termo) ?>">
        <button class="btn btn-search" type="submit">Pesquisar</button>
      </form>

      <?php if ($erro !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= escapar($erro) ?></div>
      <?php elseif (count($livros) === 0): ?>
        <div class="empty-state" role="status">
          <h2>Nenhum livro encontrado</h2>
          <p class="mb-0">
            <?= $termo !== '' ? 'Tente buscar por outro título, autor ou código.' : 'Ainda não há livros cadastrados no catálogo.' ?>
          </p>
        </div>
      <?php else: ?>
        <p class="results-count mb-3" role="status">
          <?= count($livros) ?> <?= count($livros) === 1 ? 'livro encontrado' : 'livros encontrados' ?>
          <?= $termo !== '' ? ' para “' . escapar($termo) . '”' : '' ?>
        </p>
        <section class="book-grid" aria-label="Resultados da pesquisa">
          <?php foreach ($livros as $livro): ?>
            <?php
              $disponiveis = max(0, (int) $livro['disponiveis']);
$disponivel = $disponiveis > 0;
              $isbn = preg_replace('/[^0-9Xx]/', '', (string) $livro['isbn']);
              $capaLocal = null;
              foreach (['jpg', 'png', 'webp'] as $extensaoCapa) {
                  $caminhoCapa = __DIR__ . '/img/capas/' . $isbn . '.' . $extensaoCapa;
                  if (is_file($caminhoCapa)) {
                      $capaLocal = 'img/capas/' . rawurlencode($isbn) . '.' . $extensaoCapa;
                      break;
                  }
              }
            ?>
            <article
  class="book-card"
  role="button"
  tabindex="0"
  data-bs-toggle="offcanvas"
  data-bs-target="#painelLivro"
  aria-controls="painelLivro"
  data-id="<?= (int) $livro['id'] ?>"
  data-titulo="<?= escapar($livro['titulo']) ?>"
  data-autor="<?= escapar($livro['autor']) ?>"
  data-genero="<?= escapar($livro['genero']) ?>"
  data-isbn="<?= escapar($livro['isbn']) ?>"
  data-codigo="<?= escapar($livro['codigo_bibli']) ?>"
  data-quantidade="<?= escapar($livro['quantidade']) ?>"
  data-capa="<?= escapar($capaLocal ?? '') ?>"
  data-disponiveis="<?= $disponiveis ?>"
>
              <div class="book-cover">
                <div class="cover-placeholder" aria-hidden="true">
                  <span class="cover-mark">L</span>
                  <span>Capa não disponível</span>
                </div>
                <?php if ($capaLocal !== null): ?>
                  <img
                    src="<?= escapar($capaLocal) ?>"
                    alt="Capa do livro <?= escapar($livro['titulo']) ?>"
                    loading="lazy"
                    decoding="async"
                  >
                <?php elseif ($isbn !== ''): ?>
                  <img
                    src="https://covers.openlibrary.org/b/isbn/<?= rawurlencode($isbn) ?>-M.jpg?default=false"
                    alt="Capa do livro <?= escapar($livro['titulo']) ?>"
                    loading="lazy"
                    decoding="async"
                    onload="if (this.naturalWidth <= 1) this.hidden = true"
                    onerror="this.hidden = true"
                  >
                <?php endif; ?>
                <div class="book-info">
                  <h2 class="book-title"><?= escapar($livro['titulo']) ?></h2>
                  <span class="availability <?= $disponivel ? 'is-available' : 'is-unavailable' ?>">
                    <span class="availability-dot" aria-hidden="true"></span>
                    <?= $disponivel ? 'Disponível' : 'Indisponível' ?>
                  </span>
                  <span class="book-quantity">
    <?= $disponiveis ?>
    <?= $disponiveis === 1 ? 'exemplar disponível' : 'exemplares disponíveis' ?>
</span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
      <!-- Painel lateral com detalhes do livro -->
<div
  class="offcanvas offcanvas-end"
  tabindex="-1"
  id="painelLivro"
  aria-labelledby="tituloPainelLivro"
>
  <div class="offcanvas-header">
    <h2 class="offcanvas-title" id="tituloPainelLivro">
      Detalhes do livro
    </h2>

    <button
      type="button"
      class="btn-close"
      data-bs-dismiss="offcanvas"
      aria-label="Fechar"
    ></button>
  </div>

  <div class="offcanvas-body">

    <div class="row g-3 mb-4">
      <div class="col-5">
        <img
          id="capaPainel"
          src=""
          alt="Capa do livro"
          class="img-fluid rounded"
        >
      </div>

      <div class="col-7">
        <h3 id="tituloLivroPainel">Título do livro</h3>
        <p id="autorLivroPainel" class="text-secondary"></p>

        <p>
          <strong>Gênero:</strong>
          <span id="generoLivroPainel"></span>
        </p>

        <p>
          <strong>ISBN:</strong>
          <span id="isbnLivroPainel"></span>
        </p>

        <p>
          <strong>Código:</strong>
          <span id="codigoLivroPainel"></span>
        </p>

        <p>
          <strong>Exemplares:</strong>
          <span id="quantidadeLivroPainel"></span>
        </p>
      </div>
    </div>

    <hr>

    <h4>Disponibilidade</h4>

    <p id="statusLivroPainel" class="fw-semibold">
      Situação do livro
    </p>

    <div id="alunosEmprestimoPainel">
      <!-- Os alunos com empréstimos ativos aparecerão aqui -->
    </div>

  </div>
</div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const painel = document.getElementById('painelLivro');

    painel.addEventListener('show.bs.offcanvas', async function (evento) {
        const cartao = evento.relatedTarget;

        if (!cartao) return;

        // Preenche as informações do livro
        document.getElementById('tituloLivroPainel').textContent =
            cartao.dataset.titulo;

        document.getElementById('autorLivroPainel').textContent =
            cartao.dataset.autor;

        document.getElementById('generoLivroPainel').textContent =
            cartao.dataset.genero;

        document.getElementById('isbnLivroPainel').textContent =
            cartao.dataset.isbn;

        document.getElementById('codigoLivroPainel').textContent =
            cartao.dataset.codigo;

        document.getElementById('quantidadeLivroPainel').textContent =
            cartao.dataset.quantidade;

        // Carrega a capa
        const capa = document.getElementById('capaPainel');

        if (cartao.dataset.capa) {
            capa.src = cartao.dataset.capa;
            capa.hidden = false;
        } else {
            capa.removeAttribute('src');
            capa.hidden = true;
        }

        // Atualiza a disponibilidade
        const status = document.getElementById('statusLivroPainel');

        const disponiveis = Number(cartao.dataset.disponiveis);

const disponivel = disponiveis > 0;

status.textContent = disponivel
    ? `${disponiveis} exemplar(es) disponível(is)`
    : 'Indisponível';

        status.textContent = disponivel ? 'Disponível' : 'Indisponível';

        // Prepara a área dos alunos
        const alunos = document.getElementById('alunosEmprestimoPainel');

        alunos.textContent = 'Carregando empréstimos...';

        try {
            const resposta = await fetch(
                'buscar_emprestimos.php?livro_id=' +
                encodeURIComponent(cartao.dataset.id)
            );

            if (!resposta.ok) {
                throw new Error('Erro ao consultar empréstimos.');
            }

            const dados = await resposta.json();

            if (dados.erro) {
                throw new Error(dados.erro);
            }

            alunos.textContent = '';

            if (dados.length === 0) {
                alunos.textContent = 'Nenhum empréstimo ativo para este livro.';
                return;
            }

            const titulo = document.createElement('h5');
            titulo.textContent = 'Alunos com exemplares emprestados:';
            alunos.appendChild(titulo);

            dados.forEach(function (aluno) {
                const item = document.createElement('div');
                item.classList.add('border', 'rounded', 'p-3', 'mb-2');

                const nome = document.createElement('p');
                nome.classList.add('fw-semibold', 'mb-1');
                nome.textContent = aluno.nome;

                const turma = document.createElement('p');
                turma.classList.add('mb-1');
                turma.textContent = 'Turma: ' + aluno.turma;

                const emprestimo = document.createElement('p');
                emprestimo.classList.add('mb-1');
                emprestimo.textContent =
                    'Data do empréstimo: ' + aluno.data_emprestimo;

                const devolucao = document.createElement('p');
                devolucao.classList.add('mb-0');
                devolucao.textContent =
                    'Devolução prevista: ' +
                    (aluno.data_devolucao || 'Não informada');

                item.appendChild(nome);
                item.appendChild(turma);
                item.appendChild(emprestimo);
                item.appendChild(devolucao);

                alunos.appendChild(item);
            });

        } catch (erro) {
            console.error(erro);
            alunos.textContent =
                'Não foi possível carregar os empréstimos. Tente novamente.';
        }
    });
});
</script>
  </body>
</html>
