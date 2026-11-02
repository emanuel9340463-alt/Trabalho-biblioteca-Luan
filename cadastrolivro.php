<?php

require_once 'config/conexao.php';

//Variáveis DB
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

// =====================================================
// 1. NORMALIZAÇÃO E VALIDAÇÃO
// =====================================================

function normalizarIsbn($isbn)
{
    return strtoupper(preg_replace('/[^0-9X]/i', '', (string) $isbn));
}

function normalizarTextoLivro($texto)
{
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');

    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

    if ($transliterado !== false) {
        $texto = $transliterado;
    }

    return preg_replace('/[^a-z0-9]/', '', $texto);
}

function correspondeLivro($tituloBuscado, $autorBuscado, $tituloEncontrado, $autoresEncontrados)
{
    if (trim($tituloBuscado) === '' || trim($autorBuscado) === '') {
        return false;
    }

    $titulo1 = normalizarTextoLivro($tituloBuscado);
    $titulo2 = normalizarTextoLivro($tituloEncontrado);

    if ($titulo1 === '' || $titulo2 === '') {
        return false;
    }

    similar_text($titulo1, $titulo2, $similaridadeTitulo);

    if ($similaridadeTitulo < 80) {
        return false;
    }

    foreach ((array) $autoresEncontrados as $autorEncontrado) {
        $autor1 = normalizarTextoLivro($autorBuscado);
        $autor2 = normalizarTextoLivro($autorEncontrado);

        if ($autor2 === '') {
            continue;
        }

        similar_text($autor1, $autor2, $similaridadeAutor);

        if ($similaridadeAutor >= 75) {
            return true;
        }
    }

    return false;
}

// =====================================================
// 2. DOWNLOAD DE CONTEÚDO
// =====================================================

function baixarConteudo($url, $limiteBytes = 1024 * 1024, $hostsPermitidos = [])
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if (
        parse_url($url, PHP_URL_SCHEME) !== 'https' ||
        !in_array($host, $hostsPermitidos, true)
    ) {
        return null;
    }

    $conteudo = '';
    $limiteExcedido = false;

    $curl = curl_init($url);

    if ($curl === false) {
        return null;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'BibliotecaLuan/1.0',

        CURLOPT_WRITEFUNCTION => function ($curl, $dados) use (
            &$conteudo,
            $limiteBytes,
            &$limiteExcedido
        ) {
            if (strlen($conteudo) + strlen($dados) > $limiteBytes) {
                $limiteExcedido = true;
                return 0;
            }

            $conteudo .= $dados;
            return strlen($dados);
        },
    ]);

    $executado = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if (
        $executado === false ||
        $status !== 200 ||
        $conteudo === '' ||
        $limiteExcedido
    ) {
        return null;
    }

    return $conteudo;
}

// =====================================================
// 3. DOWNLOAD E VALIDAÇÃO DE IMAGENS
// =====================================================

function extensaoImagemValida($imagem)
{
    if (!is_string($imagem) || $imagem === '') {
        return null;
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
        $dimensoes[0] < 50 ||
        $dimensoes[1] < 50
    ) {
        return null;
    }

    return $extensoesPermitidas[$tipo];
}

function baixarImagem($url, $limiteBytes = 5 * 1024 * 1024)
{
    $hostsPermitidos = [
        'covers.openlibrary.org',
        'books.google.com',
        'books.googleusercontent.com'
    ];

    $imagem = baixarConteudo($url, $limiteBytes, $hostsPermitidos);

    if ($imagem === null || extensaoImagemValida($imagem) === null) {
        return null;
    }

    return $imagem;
}

// =====================================================
// 4. SALVAMENTO DA CAPA
// =====================================================

function salvarImagemCapa($isbn, $imagem)
{
    $isbn = normalizarIsbn($isbn);

    if (!in_array(strlen($isbn), [10, 13], true)) {
        return false;
    }

    $extensao = extensaoImagemValida($imagem);

    if ($extensao === null) {
        return false;
    }

    $diretorioCapas = __DIR__ . DIRECTORY_SEPARATOR
        . 'img' . DIRECTORY_SEPARATOR . 'capas';

    if (
        !is_dir($diretorioCapas) &&
        !mkdir($diretorioCapas, 0755, true) &&
        !is_dir($diretorioCapas)
    ) {
        return false;
    }

    $caminho = $diretorioCapas . DIRECTORY_SEPARATOR
        . $isbn . '.' . $extensao;

    $temporario = $caminho . '.' . bin2hex(random_bytes(6)) . '.tmp';

    if (file_put_contents($temporario, $imagem, LOCK_EX) === false) {
        return false;
    }

    if (!rename($temporario, $caminho)) {
        @unlink($temporario);
        return false;
    }

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extensaoAnterior) {
        if ($extensaoAnterior === $extensao) {
            continue;
        }

        $anterior = $diretorioCapas . DIRECTORY_SEPARATOR
            . $isbn . '.' . $extensaoAnterior;

        if (is_file($anterior)) {
            @unlink($anterior);
        }
    }

    return true;
}

// =====================================================
// 5. AUXILIAR PARA EXTRAIR CAPAS DO GOOGLE BOOKS
// =====================================================

function extrairCapaGoogle($item, $isbn)
{
    $links = $item['volumeInfo']['imageLinks'] ?? [];

    foreach (['thumbnail', 'smallThumbnail'] as $tipo) {
        $url = $links[$tipo] ?? null;

        if (!is_string($url)) {
            continue;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!in_array($host, [
            'books.google.com',
            'books.googleusercontent.com'
        ], true)) {
            continue;
        }

        $url = preg_replace('/^http:/i', 'https:', $url);

        $imagem = baixarImagem($url);

        if ($imagem !== null && salvarImagemCapa($isbn, $imagem)) {
            return true;
        }
    }

    return false;
}

// =====================================================
// 6. BUSCA NO OPEN LIBRARY POR ISBN
// =====================================================

function buscarCapaOpenLibraryISBN($isbn)
{
    $url = 'https://covers.openlibrary.org/b/isbn/'
        . rawurlencode($isbn)
        . '-M.jpg?default=false';

    $imagem = baixarImagem($url);

    if ($imagem !== null && salvarImagemCapa($isbn, $imagem)) {
        return 'openlibrary';
    }

    return false;
}

// =====================================================
// 7. BUSCA NO GOOGLE BOOKS POR ISBN
// =====================================================

function buscarCapaGoogleISBN($isbn)
{
    $url = 'https://www.googleapis.com/books/v1/volumes?'
        . http_build_query([
            'q' => 'isbn:' . $isbn,
            'maxResults' => 5
        ]);

    $resposta = baixarConteudo(
        $url,
        1024 * 1024,
        ['www.googleapis.com']
    );

    if ($resposta === null) {
        return false;
    }

    $dados = json_decode($resposta, true);

    if (!is_array($dados)) {
        return false;
    }

    foreach (($dados['items'] ?? []) as $item) {
        if (extrairCapaGoogle($item, $isbn)) {
            return 'google-books';
        }
    }

    return false;
}

// =====================================================
// 8. BUSCA NO GOOGLE BOOKS POR TÍTULO E AUTOR
// =====================================================

function buscarCapaGoogleTitulo($isbn, $titulo, $autor)
{
    $url = 'https://www.googleapis.com/books/v1/volumes?'
        . http_build_query([
            'q' => 'intitle:' . $titulo . ' inauthor:' . $autor,
            'maxResults' => 10
        ]);

    $resposta = baixarConteudo(
        $url,
        1024 * 1024,
        ['www.googleapis.com']
    );

    if ($resposta === null) {
        return false;
    }

    $dados = json_decode($resposta, true);

    if (!is_array($dados)) {
        return false;
    }

    foreach (($dados['items'] ?? []) as $item) {
        $info = $item['volumeInfo'] ?? [];

        if (!correspondeLivro(
            $titulo,
            $autor,
            $info['title'] ?? '',
            $info['authors'] ?? []
        )) {
            continue;
        }

        if (extrairCapaGoogle($item, $isbn)) {
            return 'google-books-titulo';
        }
    }

    return false;
}

// =====================================================
// 9. BUSCA NO OPEN LIBRARY POR TÍTULO E AUTOR
// =====================================================

function buscarCapaOpenLibraryTitulo($isbn, $titulo, $autor)
{
    $url = 'https://openlibrary.org/search.json?'
        . http_build_query([
            'title' => $titulo,
            'author' => $autor,
            'limit' => 10,
            'fields' => 'title,author_name,cover_i'
        ]);

    $resposta = baixarConteudo(
        $url,
        1024 * 1024,
        ['openlibrary.org']
    );

    if ($resposta === null) {
        return false;
    }

    $dados = json_decode($resposta, true);

    if (!is_array($dados)) {
        return false;
    }

    foreach (($dados['docs'] ?? []) as $livro) {
        if (empty($livro['cover_i'])) {
            continue;
        }

        if (!correspondeLivro(
            $titulo,
            $autor,
            $livro['title'] ?? '',
            $livro['author_name'] ?? []
        )) {
            continue;
        }

        $urlCapa = 'https://covers.openlibrary.org/b/id/'
            . (int) $livro['cover_i']
            . '-M.jpg?default=false';

        $imagem = baixarImagem($urlCapa);

        if ($imagem !== null && salvarImagemCapa($isbn, $imagem)) {
            return 'openlibrary-titulo';
        }
    }

    return false;
}

// =====================================================
// 10. FUNÇÃO PRINCIPAL DE BUSCA DE CAPAS
// =====================================================

function salvarCapaPorIsbn($isbn, $arquivoEnviado, $titulo, $autor)
{
    $isbn = normalizarIsbn($isbn);

    // Upload manual tem prioridade
    if ($arquivoEnviado !== null) {
        $imagemEnviada = file_get_contents($arquivoEnviado['tmp_name']);

        if (
            $imagemEnviada !== false &&
            salvarImagemCapa($isbn, $imagemEnviada)
        ) {
            return 'upload';
        }

        return false;
    }

    // 1. Open Library por ISBN
    $resultado = buscarCapaOpenLibraryISBN($isbn);

    if ($resultado !== false) {
        return $resultado;
    }

    // 2. Google Books por ISBN
    $resultado = buscarCapaGoogleISBN($isbn);

    if ($resultado !== false) {
        return $resultado;
    }

    // Sem dados suficientes, encerra a busca
    if (trim($titulo) === '' || trim($autor) === '') {
        return false;
    }

    // 3. Google Books por título e autor
    $resultado = buscarCapaGoogleTitulo($isbn, $titulo, $autor);

    if ($resultado !== false) {
        return $resultado;
    }

    // 4. Open Library por título e autor
    $resultado = buscarCapaOpenLibraryTitulo($isbn, $titulo, $autor);

    if ($resultado !== false) {
        return $resultado;
    }

    return false;
}

// =====================================================
// 11. VALIDAÇÃO DO UPLOAD
// =====================================================

function validarCapaEnviada($arquivo)
{
    if (
        !is_array($arquivo) ||
        !isset($arquivo['error']) ||
        $arquivo['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if (
        $arquivo['error'] !== UPLOAD_ERR_OK ||
        !isset($arquivo['tmp_name'], $arquivo['size']) ||
        $arquivo['size'] > 5 * 1024 * 1024 ||
        !is_uploaded_file($arquivo['tmp_name'])
    ) {
        return false;
    }

    $imagem = file_get_contents($arquivo['tmp_name']);

    return $imagem !== false &&
        extensaoImagemValida($imagem) !== null
            ? $arquivo
            : false;
}

// =====================================================
// 12. PROCESSAMENTO DO FORMULÁRIO
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = isset($_POST['titulo']) && is_string($_POST['titulo'])
        ? trim($_POST['titulo']) : '';

    $autor = isset($_POST['autor']) && is_string($_POST['autor'])
        ? trim($_POST['autor']) : '';

    $categoria = isset($_POST['genero']) && is_string($_POST['genero'])
        ? trim($_POST['genero']) : '';

    $isbnInformado = isset($_POST['isbn']) && is_string($_POST['isbn'])
        ? trim($_POST['isbn']) : '';

    $isbn = normalizarIsbn($isbnInformado);

    $codigo_bibli = isset($_POST['codigo_bibli']) && is_string($_POST['codigo_bibli'])
        ? trim($_POST['codigo_bibli']) : '';

    $quantidadeInformada = isset($_POST['quantidade']) && is_string($_POST['quantidade'])
        ? trim($_POST['quantidade']) : '';

    $quantidadeValidada = filter_var($quantidadeInformada, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    $quantidade = $quantidadeInformada;

    $capaEnviada = validarCapaEnviada($_FILES['capa'] ?? null);

    if ($capaEnviada === false) {
        $erro = 'A capa deve ser uma imagem JPG, PNG ou WEBP válida, com até 5 MB.';

    } elseif (
        $titulo === '' ||
        !in_array(strlen($isbn), [10, 13], true) ||
        $codigo_bibli === '' ||
        $quantidadeValidada === false
    ) {
        $erro = 'Preencha os campos obrigatórios, informe um ISBN de 10 ou 13 caracteres e uma quantidade inteira maior que zero.';

    } else {

        $stmtVerifica = $conn->prepare(
            'SELECT id FROM livros WHERE titulo = ? LIMIT 1'
        );

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

                    $stmt = $conn->prepare(
                        'INSERT INTO livros
                        (titulo, autor, genero, isbn, codigo_bibli, quantidade)
                        VALUES (?, ?, ?, ?, ?, ?)'
                    );

                    if (!$stmt) {
                        $erro = 'Não foi possível preparar o cadastro do livro.';

                    } else {

                        $stmt->bind_param(
                            'sssssi',
                            $titulo,
                            $autor,
                            $categoria,
                            $isbn,
                            $codigo_bibli,
                            $quantidadeValidada
                        );

                        if ($stmt->execute()) {

                            $fonteCapa = salvarCapaPorIsbn(
                                $isbn,
                                $capaEnviada,
                                $titulo,
                                $autor
                            );

                            $mensagensCapa = [
                                'upload' => 'Livro cadastrado e capa enviada salva com sucesso.',
                                'openlibrary' => 'Livro cadastrado e capa encontrada na Open Library pelo ISBN.',
                                'google-books' => 'Livro cadastrado e capa encontrada no Google Books pelo ISBN.',
                                'google-books-titulo' => 'Livro cadastrado e capa encontrada no Google Books pelo título e autor.',
                                'openlibrary-titulo' => 'Livro cadastrado e capa encontrada na Open Library pelo título e autor.'
                            ];

                            $mensagem = $mensagensCapa[$fonteCapa] ??
                                'Livro cadastrado, mas não foi possível encontrar uma capa. Você pode enviar uma imagem manualmente posteriormente.';

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
                                            <div class="alert <?= $fonteCapa !== false ? 'alert-success' : 'alert-warning' ?>" role="alert">
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