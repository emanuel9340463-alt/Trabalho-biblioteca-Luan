<?php
session_start();
require_once __DIR__ . '/config/conexao.php';

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$turmas = [];
$alunos = [];
$turmaOrigem = isset($_GET['turma_origem']) && is_string($_GET['turma_origem'])
    ? trim($_GET['turma_origem'])
    : '';
$erro = '';

try {
    $stmtTurmas = $conn->prepare('SELECT DISTINCT turma FROM alunos ORDER BY turma');
    if (!$stmtTurmas || !$stmtTurmas->execute()) {
        throw new mysqli_sql_exception($conn->error);
    }

    $turmaRows = $stmtTurmas->get_result()->fetch_all(MYSQLI_ASSOC);
    $turmas = array_column($turmaRows, 'turma');
    $stmtTurmas->close();

    if ($turmaOrigem !== '') {
        $stmtAlunos = $conn->prepare(
            'SELECT id, nome FROM alunos WHERE turma = ? ORDER BY nome'
        );
        if (!$stmtAlunos) {
            throw new mysqli_sql_exception($conn->error);
        }
        if (!$stmtAlunos->bind_param('s', $turmaOrigem) || !$stmtAlunos->execute()) {
            throw new mysqli_sql_exception($stmtAlunos->error);
        }

        $alunos = $stmtAlunos->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAlunos->close();
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Erro ao carregar turmas para promoção: ' . $exception->getMessage());
    $erro = 'Não foi possível carregar as turmas e os alunos. Tente novamente mais tarde.';
}

$conn->close();

function escaparPromocaoAluno($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$mensagensResultado = [
    'sucesso' => ['success', 'A promoção dos alunos selecionados foi concluída.'],
    'dados_invalidos' => ['danger', 'Selecione uma turma, alunos e uma turma de destino válidos.'],
    'mesma_turma' => ['warning', 'A turma de origem e a turma de destino precisam ser diferentes.'],
    'selecao_desatualizada' => ['warning', 'A lista de alunos mudou. Atualize a turma de origem e tente novamente.'],
    'erro' => ['danger', 'Não foi possível concluir a promoção. Tente novamente.'],
];
$resultado = isset($_GET['resultado']) && is_string($_GET['resultado'])
    ? $_GET['resultado']
    : '';
$quantidadePromovida = isset($_GET['quantidade']) && ctype_digit((string) $_GET['quantidade'])
    ? (int) $_GET['quantidade']
    : 0;

$pageTitle = 'Promover alunos';
$pageDescription = 'Selecione os alunos que mudarão de turma e confira a operação antes de confirmar.';
$pageStyles = ['css/promover_alunos.css'];
$activePage = 'promover_alunos';
require 'includes/header.php';
?>
<?php if (isset($mensagensResultado[$resultado])): ?>
    <div class="alert alert-<?= $mensagensResultado[$resultado][0] ?>" role="status">
        <?php if ($resultado === 'sucesso' && $quantidadePromovida > 0): ?>
            <?= $quantidadePromovida ?> <?= $quantidadePromovida === 1 ? 'aluno promovido' : 'alunos promovidos' ?> com sucesso.
        <?php else: ?>
            <?= escaparPromocaoAluno($mensagensResultado[$resultado][1]) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escaparPromocaoAluno($erro) ?></div>
<?php else: ?>
    <section class="surface-card mb-4" aria-labelledby="turmaOrigemTitulo">
        <h2 class="surface-title" id="turmaOrigemTitulo">1. Escolha a turma de origem</h2>
        <p class="surface-subtitle">Os alunos cadastrados nessa turma serão exibidos para seleção.</p>
        <?php if ($turmas === []): ?>
            <div class="alert alert-warning mb-0" role="status">
                Não há turmas cadastradas para promover.
            </div>
        <?php else: ?>
            <form method="get" action="promover_alunos.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-7 col-lg-5">
                    <label class="form-label" for="turma-origem">Turma de origem</label>
                    <select class="form-select" id="turma-origem" name="turma_origem" required>
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option
                                value="<?= escaparPromocaoAluno($turma) ?>"
                                <?= $turmaOrigem === $turma ? 'selected' : '' ?>
                            ><?= escaparPromocaoAluno($turma) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <button class="btn btn-primary" type="submit">Carregar alunos</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($turmaOrigem !== ''): ?>
        <?php if ($alunos === []): ?>
            <div class="empty-state" role="status">
                <h2>Nenhum aluno encontrado</h2>
                <p class="mb-0">A turma selecionada não possui alunos cadastrados.</p>
            </div>
        <?php else: ?>
            <form
                class="surface-card"
                method="post"
                action="php/actions/promover_alunos.php"
                data-promotion-form
                data-turma-origem="<?= escaparPromocaoAluno($turmaOrigem) ?>"
            >
                <input type="hidden" name="csrf_token" value="<?= escaparPromocaoAluno($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="turma_origem" value="<?= escaparPromocaoAluno($turmaOrigem) ?>">

                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="surface-title">2. Selecione os alunos</h2>
                        <p class="surface-subtitle mb-0">
                            Turma <?= escaparPromocaoAluno($turmaOrigem) ?> · <?= count($alunos) ?>
                            <?= count($alunos) === 1 ? 'aluno' : 'alunos' ?>
                        </p>
                    </div>
                    <span class="promotion-selected-count" id="promotionSelectedCount" role="status" aria-live="polite">
                        0 selecionados
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-select-all>
                        Selecionar todos
                    </button>
                    <button class="btn btn-outline-primary btn-sm" type="button" data-deselect-all>
                        Desmarcar todos
                    </button>
                </div>

                <fieldset class="mb-4">
                    <legend class="visually-hidden">Alunos da turma <?= escaparPromocaoAluno($turmaOrigem) ?></legend>
                    <div class="promotion-student-list list-group">
                        <?php foreach ($alunos as $aluno): ?>
                            <label class="promotion-student-option list-group-item">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="alunos[]"
                                    value="<?= (int) $aluno['id'] ?>"
                                    data-student-checkbox
                                >
                                <span><?= escaparPromocaoAluno($aluno['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="turma-destino">3. Turma de destino</label>
                        <input
                            class="form-control"
                            type="text"
                            id="turma-destino"
                            name="turma_destino"
                            list="turmas-destino"
                            maxlength="5"
                            autocomplete="off"
                            placeholder="Ex.: 2A"
                            required
                        >
                        <datalist id="turmas-destino">
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?= escaparPromocaoAluno($turma) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-primary" type="submit">Promover selecionados</button>
                    </div>
                </div>
                <div class="mt-3" data-promotion-error role="alert" hidden></div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<script src="js/promover_alunos.js?v=1"></script>
<?php require 'includes/footer.php'; ?>
