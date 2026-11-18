<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';

function redirecionarPromocaoAluno($resultado, $quantidade = null)
{
    $parametros = ['resultado' => $resultado];
    if ($quantidade !== null) {
        $parametros['quantidade'] = $quantidade;
    }

    header('Location: ../../promover_alunos.php?' . http_build_query($parametros), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionarPromocaoAluno('dados_invalidos');
}

$tokenEnviado = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';
$tokenSessao = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
    ? $_SESSION['csrf_token']
    : '';
if ($tokenSessao === '' || !hash_equals($tokenSessao, $tokenEnviado)) {
    redirecionarPromocaoAluno('dados_invalidos');
}

$turmaOrigem = isset($_POST['turma_origem']) && is_string($_POST['turma_origem'])
    ? trim($_POST['turma_origem'])
    : '';
$turmaDestino = isset($_POST['turma_destino']) && is_string($_POST['turma_destino'])
    ? trim($_POST['turma_destino'])
    : '';
$alunosEnviados = $_POST['alunos'] ?? null;

if (
    preg_match('/\A.{1,5}\z/u', $turmaOrigem) !== 1 ||
    preg_match('/\A.{1,5}\z/u', $turmaDestino) !== 1 ||
    !is_array($alunosEnviados) ||
    $alunosEnviados === []
) {
    redirecionarPromocaoAluno('dados_invalidos');
}

if (strcasecmp($turmaOrigem, $turmaDestino) === 0) {
    redirecionarPromocaoAluno('mesma_turma');
}

$idsAlunos = [];
foreach ($alunosEnviados as $idEnviado) {
    if (!is_string($idEnviado) && !is_int($idEnviado)) {
        redirecionarPromocaoAluno('dados_invalidos');
    }

    $idAluno = filter_var($idEnviado, FILTER_VALIDATE_INT);
    if ($idAluno === false || $idAluno < 1 || isset($idsAlunos[$idAluno])) {
        redirecionarPromocaoAluno('dados_invalidos');
    }

    $idsAlunos[$idAluno] = $idAluno;
}
$idsAlunos = array_values($idsAlunos);

$transacaoIniciada = false;

try {
    if (!$conn->begin_transaction()) {
        throw new mysqli_sql_exception($conn->error);
    }
    $transacaoIniciada = true;

    $placeholders = implode(', ', array_fill(0, count($idsAlunos), '?'));
    $stmt = $conn->prepare(
        "UPDATE alunos
         SET turma = ?
         WHERE turma = ?
           AND id IN ($placeholders)"
    );
    if (!$stmt) {
        throw new mysqli_sql_exception($conn->error);
    }

    $tipos = 'ss' . str_repeat('i', count($idsAlunos));
    $parametros = array_merge([$turmaDestino, $turmaOrigem], $idsAlunos);
    $argumentos = [$tipos];
    foreach ($parametros as $indice => &$parametro) {
        $argumentos[] = &$parametro;
    }
    unset($parametro);

    if (!call_user_func_array([$stmt, 'bind_param'], $argumentos)) {
        throw new mysqli_sql_exception($stmt->error);
    }
    if (!$stmt->execute()) {
        throw new mysqli_sql_exception($stmt->error);
    }

    $alunosAtualizados = $stmt->affected_rows;
    $stmt->close();

    if ($alunosAtualizados !== count($idsAlunos)) {
        $conn->rollback();
        $transacaoIniciada = false;
        redirecionarPromocaoAluno('selecao_desatualizada');
    }

    if (!$conn->commit()) {
        throw new mysqli_sql_exception($conn->error);
    }
    $transacaoIniciada = false;

    redirecionarPromocaoAluno('sucesso', $alunosAtualizados);
} catch (Throwable $exception) {
    if ($transacaoIniciada) {
        $conn->rollback();
    }
    error_log('Erro ao promover alunos: ' . $exception->getMessage());
    redirecionarPromocaoAluno('erro');
}
