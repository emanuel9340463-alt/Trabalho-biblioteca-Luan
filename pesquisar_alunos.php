<?php
require_once __DIR__ . '/config/seguranca.php';
exigirAutenticacao();
require_once __DIR__ . '/config/conexao.php';

$tipoTermoValido = !isset($_GET['q']) || is_string($_GET['q']);
$termo = $tipoTermoValido ? (isset($_GET['q']) ? trim($_GET['q']) : '') : '';
$alunos = [];
$erro = $tipoTermoValido ? '' : 'O termo de pesquisa é inválido.';

try {
    if (!textoTamanhoValido($termo, 0, 100)) {
        $erro = 'O termo de pesquisa deve ter no máximo 100 caracteres.';
    } elseif ($termo === '') {
        $stmt = $conn->prepare(
            'SELECT id, nome, data_nascimento, turma FROM alunos ORDER BY turma, nome'
        );
    } else {
        $stmt = $conn->prepare(
            'SELECT id, nome, data_nascimento, turma
             FROM alunos
             WHERE nome LIKE CONCAT("%", ?, "%")
                OR turma LIKE CONCAT("%", ?, "%")
             ORDER BY turma, nome'
        );

        if ($stmt && !$stmt->bind_param('ss', $termo, $termo)) {
            throw new mysqli_sql_exception($stmt->error);
        }
    }

    if ($erro === '') {
        if (!$stmt) {
            throw new mysqli_sql_exception($conn->error);
        }

        if (!$stmt->execute()) {
            throw new mysqli_sql_exception($stmt->error);
        }

        $alunos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Erro ao consultar alunos: ' . $exception->getMessage());
    $erro = 'Não foi possível carregar os alunos. Tente novamente mais tarde.';
}

$conn->close();

$alunosPorTurma = [];
foreach ($alunos as $aluno) {
    $alunosPorTurma[$aluno['turma']][] = $aluno;
}

function escaparConsultaAlunos($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Pesquisar alunos';
$pageDescription = 'Consulte os alunos cadastrados por nome ou turma.';
$activePage = 'pesquisar_alunos';
$pageStyles = ['css/pesquisa.css'];
require 'includes/header.php';
?>
<section class="surface-card mb-4" aria-labelledby="filtroAlunosTitulo">
    <h2 class="surface-title" id="filtroAlunosTitulo">Localizar alunos</h2>
    <p class="surface-subtitle">Pesquise pelo nome do aluno ou pela turma.</p>
    <form
        method="get"
        action="pesquisar_alunos.php"
        class="row g-3 align-items-end"
        data-live-search
        data-search-results="#resultados-alunos"
    >
        <div class="col-12 col-md-8">
            <label for="busca-alunos" class="form-label">Nome ou turma</label>
            <input
                type="search"
                name="q"
                id="busca-alunos"
                class="form-control"
                value="<?= escaparConsultaAlunos($termo) ?>"
                placeholder="Ex.: Maria da Silva ou 2A"
            >
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($termo !== ''): ?>
                <a href="pesquisar_alunos.php" class="btn btn-outline-primary">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div id="resultados-alunos" aria-live="polite">
<?php if ($erro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escaparConsultaAlunos($erro) ?></div>
<?php elseif ($alunos === []): ?>
    <div class="empty-state" role="status">
        <h2>Nenhum aluno encontrado</h2>
        <p class="mb-0">
            <?= $termo === '' ? 'Ainda não há alunos cadastrados.' : 'Tente pesquisar por outro nome ou turma.' ?>
        </p>
    </div>
<?php else: ?>
    <section class="surface-card" aria-labelledby="listaAlunosTitulo">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="surface-title mb-0" id="listaAlunosTitulo">Alunos cadastrados</h2>
            <span class="text-body-secondary">
                <?= count($alunos) ?> <?= count($alunos) === 1 ? 'aluno encontrado' : 'alunos encontrados' ?>
            </span>
        </div>
        <div class="btn-group mb-3" role="group" aria-label="Modo de exibição dos alunos">
            <input
                type="radio"
                class="btn-check"
                name="visualizacao-alunos"
                id="visualizacao-por-turma"
                value="grupos"
                data-student-view-control
                checked
            >
            <label class="btn btn-outline-primary" for="visualizacao-por-turma">Por turma</label>
            <input
                type="radio"
                class="btn-check"
                name="visualizacao-alunos"
                id="visualizacao-individual"
                value="individual"
                data-student-view-control
            >
            <label class="btn btn-outline-primary" for="visualizacao-individual">Individualmente</label>
        </div>
        <div data-student-view="grupos">
            <div class="student-class-groups">
                <?php foreach ($alunosPorTurma as $turma => $alunosDaTurma): ?>
                    <details class="student-class-group">
                        <summary class="student-class-summary">
                            <span class="student-class-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg>
                            </span>
                            <span class="student-class-heading">
                                <span class="student-class-eyebrow">GRUPO DE ALUNOS</span>
                                <strong>Turma <?= escaparConsultaAlunos($turma) ?></strong>
                            </span>
                            <span class="student-class-count">
                                <?= count($alunosDaTurma) ?>
                                <?= count($alunosDaTurma) === 1 ? 'aluno' : 'alunos' ?>
                            </span>
                            <span class="student-class-chevron" aria-hidden="true"></span>
                        </summary>
                        <ul class="student-class-students">
                            <?php foreach ($alunosDaTurma as $aluno): ?>
                                <li class="student-class-student">
                                    <span class="student-class-student-name"><?= escaparConsultaAlunos($aluno['nome']) ?></span>
                                    <span class="student-class-student-meta">
                                        Nascimento: <?= date('d/m/Y', strtotime($aluno['data_nascimento'])) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
        <div data-student-view="individual" hidden>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Nome</th>
                            <th scope="col">Data de nascimento</th>
                            <th scope="col">Turma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $aluno): ?>
                            <tr>
                                <td><?= escaparConsultaAlunos($aluno['nome']) ?></td>
                                <td><?= date('d/m/Y', strtotime($aluno['data_nascimento'])) ?></td>
                                <td><?= escaparConsultaAlunos($aluno['turma']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
</div>
<script src="js/pesquisa-ao-vivo.js?v=3"></script>
<?php require 'includes/footer.php'; ?>
