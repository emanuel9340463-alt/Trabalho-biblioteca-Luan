<?php
require_once __DIR__ . '/../../config/conexao.php';

// Responde em JSON apenas com livros que possuem exemplares disponíveis.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

if ($termo === '') {
    echo json_encode(['livros' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT
            livros.id,
            livros.titulo,
            livros.autor,
            livros.isbn,
            GREATEST(
                0,
                CAST(livros.quantidade AS SIGNED) - COUNT(emprestimos.id)
            ) AS disponiveis
        FROM livros
        LEFT JOIN emprestimos
            ON emprestimos.livro_id = livros.id
            AND emprestimos.data_devolucao_real IS NULL
        WHERE livros.titulo LIKE CONCAT('%', ?, '%')
           OR livros.autor LIKE CONCAT('%', ?, '%')
           OR livros.isbn LIKE CONCAT('%', ?, '%')
        GROUP BY
            livros.id,
            livros.titulo,
            livros.autor,
            livros.isbn,
            livros.quantidade
        HAVING disponiveis > 0
        ORDER BY livros.titulo
        LIMIT 25";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new mysqli_sql_exception($conn->error);
    }

    if (!$stmt->bind_param('sss', $termo, $termo, $termo)) {
        throw new mysqli_sql_exception($stmt->error);
    }
    if (!$stmt->execute()) {
        throw new mysqli_sql_exception($stmt->error);
    }

    $resultado = $stmt->get_result();
    $livros = $resultado->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();

    echo json_encode(['livros' => $livros], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $erro) {
    error_log('Erro ao pesquisar livros para empréstimo: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(
        ['erro' => 'Não foi possível pesquisar livros. Tente novamente.'],
        JSON_UNESCAPED_UNICODE
    );
}
