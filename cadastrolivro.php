<?php
require_once 'config/conexao.php';

$mensagem = '';
$erro = '';
$fonteCapa = false;
$titulo = '';
$autor = '';
$categoria = '';
$isbn = '';
$codigo_bibli = '';
$quantidade = '1';

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function baixarImagem($url, $limiteBytes = 5 * 1024 * 1024)
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $imagem = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'BibliotecaLuan/1.0',
        CURLOPT_WRITEFUNCTION => function ($curl, $dados) use (&$imagem, $limiteBytes) {
            if (strlen($imagem) + strlen($dados) > $limiteBytes) {
                return 0;
            }

            $imagem .= $dados;
            return strlen($dados);
        },
    ]);

    $executado = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($executado === false || $status !== 200 || $imagem === '') {
        return null;
    }

    return $imagem;
}

function extensaoImagemValida($imagem)
{
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->buffer($imagem);
    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $dimensoes = @getimagesizefromstring($imagem);
    if (
        !isset($extensoesPermitidas[$tipo]) ||
        $dimensoes === false ||
        $dimensoes[0] < 20 ||
        $dimensoes[1] < 20
    ) {
        return null;
    }

    return $extensoesPermitidas[$tipo];
}

function salvarImagemCapa($isbn, $imagem)
{
    $extensao = extensaoImagemValida($imagem);
    if ($extensao === null) {
        return false;
    }

    $diretorioCapas = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'capas';
    if (!is_dir($diretorioCapas) && !mkdir($diretorioCapas, 0755, true) && !is_dir($diretorioCapas)) {
        return false;
    }

    foreach (['jpg', 'png', 'webp'] as $extensaoAnterior) {
        $caminhoAnterior = $diretorioCapas . DIRECTORY_SEPARATOR . $isbn . '.' . $extensaoAnterior;
        if ($extensaoAnterior !== $extensao && is_file($caminhoAnterior) && !unlink($caminhoAnterior)) {
            return false;
        }
    }

    $caminho = $diretorioCapas . DIRECTORY_SEPARATOR . $isbn . '.' . $extensao;
    return file_put_contents($caminho, $imagem, LOCK_EX) !== false;
}

function salvarCapaPorIsbn($isbn, $arquivoEnviado)
{
    if ($arquivoEnviado !== null) {
        $imagemEnviada = file_get_contents($arquivoEnviado['tmp_name']);
        return $imagemEnviada !== false && salvarImagemCapa($isbn, $imagemEnviada)
            ? 'upload'
            : false;
    }

    $imagemOpenLibrary = baixarImagem(
        'https://covers.openlibrary.org/b/isbn/' . rawurlencode($isbn) . '-M.jpg?default=false'
    );
    if ($imagemOpenLibrary !== null && salvarImagemCapa($isbn, $imagemOpenLibrary)) {
        return 'openlibrary';
    }

    $respostaGoogle = baixarImagem(
        'https://www.googleapis.com/books/v1/volumes?q=isbn:' . rawurlencode($isbn) . '&maxResults=5',
        1024 * 1024
    );
    $dadosGoogle = $respostaGoogle === null ? null : json_decode($respostaGoogle, true);

    foreach (($dadosGoogle['items'] ?? []) as $item) {
        $linksCapa = $item['volumeInfo']['imageLinks'] ?? [];
        foreach (['thumbnail', 'smallThumbnail'] as $tipoCapa) {
            $urlCapa = $linksCapa[$tipoCapa] ?? '';
            $hostCapa = is_string($urlCapa) ? parse_url($urlCapa, PHP_URL_HOST) : null;
            if (!in_array($hostCapa, ['books.google.com', 'books.googleusercontent.com'], true)) {
                continue;
            }

            $urlCapa = preg_replace('/^http:/i', 'https:', $urlCapa);
            $imagemGoogle = baixarImagem($urlCapa);
            if ($imagemGoogle !== null && salvarImagemCapa($isbn, $imagemGoogle)) {
                return 'google-books';
            }
        }
    }

    return false;
}

function validarCapaEnviada($arquivo)
{
    if (!is_array($arquivo) || !isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK || !isset($arquivo['tmp_name'], $arquivo['size'])
        || $arquivo['size'] > 5 * 1024 * 1024 || !is_uploaded_file($arquivo['tmp_name'])) {
        return false;
    }

    $imagem = file_get_contents($arquivo['tmp_name']);
    return $imagem !== false && extensaoImagemValida($imagem) !== null
        ? $arquivo
        : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = isset($_POST['titulo']) && is_string($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $autor = isset($_POST['autor']) && is_string($_POST['autor']) ? trim($_POST['autor']) : '';
    $categoria = isset($_POST['genero']) && is_string($_POST['genero']) ? trim($_POST['genero']) : '';
    $isbnInformado = isset($_POST['isbn']) && is_string($_POST['isbn']) ? trim($_POST['isbn']) : '';
    $isbn = preg_replace('/[^0-9Xx]/', '', $isbnInformado);
    $codigo_bibli = isset($_POST['codigo_bibli']) && is_string($_POST['codigo_bibli']) ? trim($_POST['codigo_bibli']) : '';
    $quantidadeInformada = isset($_POST['quantidade']) && is_string($_POST['quantidade'])
        ? trim($_POST['quantidade'])
        : '';
    $quantidadeValidada = filter_var($quantidadeInformada, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $quantidade = $quantidadeInformada;
    $capaEnviada = validarCapaEnviada($_FILES['capa'] ?? null);

    if ($capaEnviada === false) {
        $erro = 'A capa deve ser uma imagem JPG, PNG ou WEBP válida, com até 5 MB.';
    } elseif ($titulo === '' || $isbn === '' || $codigo_bibli === '' || $quantidadeValidada === false) {
        $erro = 'Preencha os campos obrigatórios e informe uma quantidade inteira maior que zero.';
    } else {
        $stmtVerifica = $conn->prepare('SELECT id FROM livros WHERE titulo = ? LIMIT 1');

        if (!$stmtVerifica) {
            $erro = 'Não foi possível verificar o cadastro do livro.';
        } else {
            $stmtVerifica->bind_param('s', $titulo);

            if (!$stmtVerifica->execute()) {
                $erro = 'Não foi possível verificar o cadastro do livro.';
            } else {
                $resultado = $stmtVerifica->get_result();

                if ($resultado->num_rows > 0) {
                    $erro = 'Este livro já está cadastrado.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO livros (titulo, autor, genero, isbn, codigo_bibli, quantidade) VALUES (?, ?, ?, ?, ?, ?)');

                    if (!$stmt) {
                        $erro = 'Não foi possível preparar o cadastro do livro.';
                    } else {
                        $stmt->bind_param('sssssi', $titulo, $autor, $categoria, $isbn, $codigo_bibli, $quantidadeValidada);

                        if ($stmt->execute()) {
                            $fonteCapa = salvarCapaPorIsbn($isbn, $capaEnviada);
                            $mensagem = $fonteCapa === 'upload'
                                ? 'Livro cadastrado e capa enviada salva com sucesso.'
                                : ($fonteCapa === 'google-books'
                                    ? 'Livro cadastrado e capa encontrada na Google Books.'
                                    : ($fonteCapa === 'openlibrary'
                                        ? 'Livro cadastrado e capa encontrada na Open Library.'
                                        : 'Livro cadastrado, mas não foi possível encontrar ou salvar a capa. Você pode tentar enviar uma imagem manualmente.'));
                            $titulo = '';
                            $autor = '';
                            $categoria = '';
                            $isbn = '';
                            $codigo_bibli = '';
                            $quantidade = '1';
                        } elseif ($stmt->errno === 1062) {
                            $erro = 'Já existe um livro cadastrado com este ISBN ou código da biblioteca.';
                        } else {
                            $erro = 'Não foi possível cadastrar o livro. Tente novamente.';
                        }
                        $stmt->close();
                    }
                }
            }
            $stmtVerifica->close();
        }
    }
}

$conn->close();
?>

<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Livro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
  </head>
  <body>
    <div class="container">
        <div class="row vh-100 justify-content-center align-items-center">
            <div class="col-lg-10">
                <div class="card login-card shadow-lg border-0 overflow-hidden">
                    <div class="row g-0">
                        
                        <div class="col-md-6 d-none d-md-block">
                            <div class="login-image">
                                <img src="img/cadastrolivro.jpg" width="400" alt="">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card-body p-5">
                                <h2 class="text-center mb-4">Cadastrar Livros</h2>
                                
                                <!-- FORMULÁRIO CORRIGIDO COM action E method -->
                                <form id="livroForm" action="cadastrolivro.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Título do Livro</label>
                                        <input type="text" name="titulo" class="form-control" id="livro" placeholder="Digite o título do livro" value="<?= escapar($titulo) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Autor</label>
                                        <input type="text" name="autor" class="form-control" id="autor" placeholder="Digite o nome do autor" value="<?= escapar($autor) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Gênero</label>
                                        <input type="text" name="genero" class="form-control" id="genero" placeholder="Digite o gênero do livro" value="<?= escapar($categoria) ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Código ISBN</label>
                                        <input type="text" name="isbn" class="form-control" id="isbn" placeholder="Digite o código ISBN" value="<?= escapar($isbn) ?>" required>
                                        <div class="isbn-cover-preview mt-2" aria-live="polite">
                                            <img id="isbnCoverPreview" src="" alt="Prévia da capa encontrada pelo ISBN" hidden>
                                            <span id="isbnCoverMessage" class="text-secondary">A capa correspondente aparecerá aqui.</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="capa">Enviar capa manualmente (opcional)</label>
                                        <input type="file" name="capa" class="form-control" id="capa" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text">JPG, PNG ou WEBP, até 5 MB. A imagem enviada terá prioridade sobre as capas encontradas pelo ISBN.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Código da Biblioteca</label>
                                        <input type="text" name="codigo_bibli" class="form-control" id="codigo_bibli" placeholder="Digite o código da biblioteca" value="<?= escapar($codigo_bibli) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="quantidade">Quantidade de exemplares</label>
                                        <input type="number" name="quantidade" class="form-control" id="quantidade" min="1" step="1" value="<?= escapar($quantidade) ?>" required>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar Livro</button>
                                    </div>

                                    <!-- EXIBIÇÃO DAS MENSAGENS DE SUCESSO OU ERRO -->
                                    <div id="mensagem" class="mt-3 text-center">
                                        <?php if (!empty($mensagem)): ?>
                                            <div class="alert <?= $fonteCapa ? 'alert-success' : 'alert-warning' ?>" role="alert">
                                                <?= escapar($mensagem) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($erro)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?= escapar($erro) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>