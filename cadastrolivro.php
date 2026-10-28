<?php
require_once 'config/conexao.php';

$mensagem = '';
$erro = '';
$capaEncontrada = false;
$titulo = '';
$autor = '';
$categoria = '';
$isbn = '';
$codigo_bibli = '';

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function salvarCapaPorIsbn($isbn)
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $url = 'https://covers.openlibrary.org/b/isbn/' . rawurlencode($isbn) . '-M.jpg?default=false';
    $imagem = '';
    $limiteBytes = 5 * 1024 * 1024;
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
        return false;
    }

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
        return false;
    }

    $diretorioCapas = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'capas';
    if (!is_dir($diretorioCapas) && !mkdir($diretorioCapas, 0755, true) && !is_dir($diretorioCapas)) {
        return false;
    }

    $caminho = $diretorioCapas . DIRECTORY_SEPARATOR . $isbn . '.' . $extensoesPermitidas[$tipo];
    return file_put_contents($caminho, $imagem, LOCK_EX) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = isset($_POST['titulo']) && is_string($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $autor = isset($_POST['autor']) && is_string($_POST['autor']) ? trim($_POST['autor']) : '';
    $categoria = isset($_POST['genero']) && is_string($_POST['genero']) ? trim($_POST['genero']) : '';
    $isbnInformado = isset($_POST['isbn']) && is_string($_POST['isbn']) ? trim($_POST['isbn']) : '';
    $isbn = preg_replace('/[^0-9Xx]/', '', $isbnInformado);
    $codigo_bibli = isset($_POST['codigo_bibli']) && is_string($_POST['codigo_bibli']) ? trim($_POST['codigo_bibli']) : '';

    if ($titulo === '' || $isbn === '' || $codigo_bibli === '') {
        $erro = 'Preencha os campos obrigatórios: título, ISBN e código da biblioteca.';
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
                    $stmt = $conn->prepare('INSERT INTO livros (titulo, autor, genero, isbn, codigo_bibli) VALUES (?, ?, ?, ?, ?)');

                    if (!$stmt) {
                        $erro = 'Não foi possível preparar o cadastro do livro.';
                    } else {
                        $stmt->bind_param('sssss', $titulo, $autor, $categoria, $isbn, $codigo_bibli);

                        if ($stmt->execute()) {
                            $capaEncontrada = salvarCapaPorIsbn($isbn);
                            $mensagem = $capaEncontrada
                                ? 'Livro cadastrado e capa salva com sucesso.'
                                : 'Livro cadastrado, mas não foi possível encontrar ou salvar a capa para este ISBN.';
                            $titulo = '';
                            $autor = '';
                            $categoria = '';
                            $isbn = '';
                            $codigo_bibli = '';
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
                                <form id="livroForm" action="cadastrolivro.php" method="POST">
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
                                        <label class="form-label">Código da Biblioteca</label>
                                        <input type="text" name="codigo_bibli" class="form-control" id="codigo_bibli" placeholder="Digite o código da biblioteca" value="<?= escapar($codigo_bibli) ?>" required>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar Livro</button>
                                    </div>

                                    <!-- EXIBIÇÃO DAS MENSAGENS DE SUCESSO OU ERRO -->
                                    <div id="mensagem" class="mt-3 text-center">
                                        <?php if (!empty($mensagem)): ?>
                                            <div class="alert <?= $capaEncontrada ? 'alert-success' : 'alert-warning' ?>" role="alert">
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