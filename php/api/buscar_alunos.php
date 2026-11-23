<?php
require_once __DIR__ . '/../../config/seguranca.php';
exigirAutenticacao(true);
require_once __DIR__ . '/../../config/conexao.php';

// Responde em JSON para a pesquisa feita pelo JavaScript.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipoTermoValido = !isset($_GET['q']) || is_string($_GET['q']);
$termo = $tipoTermoValido ? (isset($_GET['q']) ? trim($_GET['q']) : '') : '';

if (!$tipoTermoValido || !textoTamanhoValido($termo, 0, 100)) {
    http_response_code(400);
    echo json_encode(['erro' => 'O termo de pesquisa é inválido ou muito longo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($termo === '') {
    echo json_encode(['alunos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT id, nome, turma
        FROM alunos
        WHERE nome LIKE CONCAT('%', ?, '%')
           OR turma LIKE CONCAT('%', ?, '%')
        ORDER BY nome
        LIMIT 25";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new mysqli_sql_exception($conn->error);
    }

    if (!$stmt->bind_param('ss', $termo, $termo)) {
        throw new mysqli_sql_exception($stmt->error);
    }
    if (!$stmt->execute()) {
        throw new mysqli_sql_exception($stmt->error);
    }

    $resultado = $stmt->get_result();
    $alunos = $resultado->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();

    echo json_encode(['alunos' => $alunos], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $erro) {
    error_log('Erro ao pesquisar alunos para empréstimo: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(
        ['erro' => 'Não foi possível pesquisar alunos. Tente novamente.'],
        JSON_UNESCAPED_UNICODE
    );
}
