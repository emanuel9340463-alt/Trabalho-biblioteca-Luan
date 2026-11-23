<?php
require_once __DIR__ . '/config/seguranca.php';
exigirAutenticacao();
require_once 'config/conexao.php';

function formatarDataHistorico($data)
{
    return dataIsoValida($data) ? date('d/m/Y', strtotime($data)) : '—';
}

$dataInicio = isset($_GET['data_inicio']) && is_string($_GET['data_inicio'])
    ? trim($_GET['data_inicio']) : '';
$dataFim = isset($_GET['data_fim']) && is_string($_GET['data_fim'])
    ? trim($_GET['data_fim']) : '';
$erroFiltro = '';
$erroConsulta = '';

if ((isset($_GET['data_inicio']) && !is_string($_GET['data_inicio']))
    || (isset($_GET['data_fim']) && !is_string($_GET['data_fim']))) {
    $erroFiltro = 'Informe datas válidas para consultar o histórico.';
}

// Impede intervalos inválidos ou uma data inicial posterior à data final.
if ($erroFiltro === '' && (
    ($dataInicio !== '' && !dataIsoValida($dataInicio))
    || ($dataFim !== '' && !dataIsoValida($dataFim))
)) {
    $erroFiltro = 'Informe datas válidas para consultar o histórico.';
} elseif ($erroFiltro === '' && $dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim) {
    $erroFiltro = 'A data inicial não pode ser posterior à data final.';
}

$sqlEmprestimos = "SELECT
        alunos.nome AS aluno,
        alunos.turma,
        livros.titulo AS livro,
        emprestimos.data_emprestimo,
        emprestimos.data_devolucao,
        emprestimos.data_devolucao_real,
        CASE
            WHEN emprestimos.data_devolucao_real IS NOT NULL THEN 'devolvido'
            WHEN emprestimos.data_devolucao < CURDATE() THEN 'atrasado'
            ELSE 'aberto'
        END AS situacao
    FROM emprestimos
    INNER JOIN alunos ON alunos.id = emprestimos.aluno_id
    INNER JOIN livros ON livros.id = emprestimos.livro_id";

$emprestimos = [];
if ($erroFiltro === '') {
    try {
    if ($dataInicio !== '' && $dataFim !== '') {
        $stmt = $conn->prepare($sqlEmprestimos . '
            WHERE emprestimos.data_emprestimo BETWEEN ? AND ?
            ORDER BY emprestimos.data_emprestimo DESC, emprestimos.id DESC');
        $stmt->bind_param('ss', $dataInicio, $dataFim);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $emprestimos = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($dataInicio !== '') {
        $stmt = $conn->prepare($sqlEmprestimos . '
            WHERE emprestimos.data_emprestimo >= ?
            ORDER BY emprestimos.data_emprestimo DESC, emprestimos.id DESC');
        $stmt->bind_param('s', $dataInicio);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $emprestimos = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($dataFim !== '') {
        $stmt = $conn->prepare($sqlEmprestimos . '
            WHERE emprestimos.data_emprestimo <= ?
            ORDER BY emprestimos.data_emprestimo DESC, emprestimos.id DESC');
        $stmt->bind_param('s', $dataFim);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $emprestimos = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $resultado = $conn->query($sqlEmprestimos . '
            ORDER BY emprestimos.data_emprestimo DESC, emprestimos.id DESC');
        if ($resultado === false) {
            throw new RuntimeException('Não foi possível carregar o histórico: ' . $conn->error);
        }
        $emprestimos = $resultado->fetch_all(MYSQLI_ASSOC);
    }
    } catch (Throwable $erro) {
        error_log('Falha ao carregar o histórico de empréstimos: ' . $erro->getMessage());
        $erroConsulta = 'Não foi possível carregar o histórico. Tente novamente mais tarde.';
    }
}

$conn->close();

$totalEmprestimos = count($emprestimos);
$totalDevolvidos = 0;
$totalAbertos = 0;
$totalAtrasados = 0;
foreach ($emprestimos as $emprestimo) {
    if ($emprestimo['situacao'] === 'devolvido') {
        $totalDevolvidos++;
    } elseif ($emprestimo['situacao'] === 'atrasado') {
        $totalAtrasados++;
    } else {
        $totalAbertos++;
    }
}

if ($erroFiltro !== '') {
    $periodoRelatorio = 'Período inválido';
} elseif ($dataInicio !== '' && $dataFim !== '') {
    $periodoRelatorio = 'De ' . formatarDataHistorico($dataInicio) . ' até ' . formatarDataHistorico($dataFim);
} elseif ($dataInicio !== '') {
    $periodoRelatorio = 'A partir de ' . formatarDataHistorico($dataInicio);
} elseif ($dataFim !== '') {
    $periodoRelatorio = 'Até ' . formatarDataHistorico($dataFim);
} else {
    $periodoRelatorio = 'Todo o histórico';
}

$pageTitle = 'Histórico de empréstimos';
$pageDescription = 'Consulte os empréstimos por período e imprima um relatório de movimentações.';
$pageStyles = ['css/historico.css'];
$activePage = 'historico';
require 'includes/header.php';
?>
<section class="history-summary row g-3" aria-label="Resumo do período">
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Empréstimos no período</div>
                    <div class="stat-value"><?= $totalEmprestimos ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>
                </span>
            </div>
            <div class="stat-note">Retiradas registradas</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Devolvidos</div>
                    <div class="stat-value"><?= $totalDevolvidos ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 9.5 17 20 6.5"/></svg>
                </span>
            </div>
            <div class="stat-note">Com devolução registrada</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Em andamento</div>
                    <div class="stat-value"><?= $totalAbertos ?></div>
                </div>
                <span class="stat-icon is-gold" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <div class="stat-note">Dentro do prazo de devolução</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Em atraso</div>
                    <div class="stat-value"><?= $totalAtrasados ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                </span>
            </div>
            <div class="stat-note">Ainda não devolvidos no prazo</div>
        </article>
    </div>
</section>

<section class="history-filter surface-card" aria-labelledby="filtroHistoricoTitulo">
    <div class="history-filter-heading">
        <div>
            <h2 class="surface-title" id="filtroHistoricoTitulo">Filtrar por período</h2>
            <p class="surface-subtitle">O intervalo considera a data em que o empréstimo foi realizado.</p>
        </div>
        <button class="btn btn-outline-primary history-print-button" type="button" onclick="window.print()">
            Imprimir relatório
        </button>
    </div>
    <form method="get" action="historico.php" class="history-filter-form">
        <div class="history-date-field">
            <label for="data_inicio" class="form-label">Data inicial</label>
            <input
                type="date"
                name="data_inicio"
                id="data_inicio"
                class="form-control"
                value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="history-date-field">
            <label for="data_fim" class="form-label">Data final</label>
            <input
                type="date"
                name="data_fim"
                id="data_fim"
                class="form-control"
                value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="history-filter-actions">
            <button type="submit" class="btn btn-primary">Aplicar período</button>
            <a class="btn btn-secondary" href="historico.php">Limpar</a>
        </div>
    </form>
</section>

<?php if ($erroFiltro !== ''): ?>
    <div class="alert alert-danger mt-3" role="alert">
        <?= htmlspecialchars($erroFiltro, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>
<?php if ($erroConsulta !== ''): ?>
    <div class="alert alert-danger mt-3" role="alert">
        <?= htmlspecialchars($erroConsulta, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="history-report surface-card" aria-labelledby="historicoTabelaTitulo">
    <div class="history-report-heading-screen">
        <div>
            <h2 class="surface-title" id="historicoTabelaTitulo">Movimentações</h2>
            <p class="surface-subtitle"><?= $totalEmprestimos ?> registro<?= $totalEmprestimos === 1 ? '' : 's' ?> · <?= htmlspecialchars($periodoRelatorio, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <div class="history-print-heading">
        <h1>Relatório de empréstimos</h1>
        <p><?= htmlspecialchars($periodoRelatorio, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php if ($emprestimos === []): ?>
        <div class="dashboard-empty">
            <?= $erroConsulta !== '' ? 'O histórico está temporariamente indisponível.' : ($erroFiltro === '' ? 'Nenhum empréstimo encontrado para este período.' : 'Corrija o período para exibir os registros.') ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle history-table">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Turma</th>
                        <th>Livro</th>
                        <th>Data do empréstimo</th>
                        <th>Devolução prevista</th>
                        <th>Devolução real</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emprestimos as $emprestimo): ?>
                        <tr>
                            <td><?= htmlspecialchars($emprestimo['aluno'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($emprestimo['turma'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($emprestimo['livro'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= formatarDataHistorico($emprestimo['data_emprestimo']) ?></td>
                            <td><?= formatarDataHistorico($emprestimo['data_devolucao']) ?></td>
                            <td><?= formatarDataHistorico($emprestimo['data_devolucao_real']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
