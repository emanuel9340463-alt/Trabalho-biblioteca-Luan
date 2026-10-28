<?php
require_once 'config/conexao.php';

$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$livros = [];
$erro = '';

if ($termo === '') {
    $sql = 'SELECT titulo, isbn, status_livro FROM livros ORDER BY titulo';
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT titulo, isbn, status_livro FROM livros
            WHERE titulo LIKE CONCAT('%', ?, '%')
               OR autor LIKE CONCAT('%', ?, '%')
               OR isbn LIKE CONCAT('%', ?, '%')
               OR codigo_bibli LIKE CONCAT('%', ?, '%')
            ORDER BY titulo";
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
<html lang="pt-br">
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
          value="<?= escapar($termo) ?>"
        >
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
              $disponivel = livroDisponivel($livro['status_livro']);
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
            <article class="book-card">
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
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
  </body>
</html>
