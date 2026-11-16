<?php
require_once 'config/conexao.php';

function executarConsultaDashboard($conn, $sql)
{
    $resultado = $conn->query($sql);

    if ($resultado === false) {
        throw new RuntimeException('Não foi possível carregar o painel: ' . $conn->error);
    }

    return $resultado;
}

$resumoLivros = executarConsultaDashboard(
    $conn,
    "SELECT
        COUNT(l.id) AS titulos,
        COALESCE(SUM(l.quantidade), 0) AS exemplares,
        COALESCE(SUM(GREATEST(CAST(l.quantidade AS SIGNED) - COALESCE(a.ativos, 0), 0)), 0) AS disponiveis
     FROM livros l
     LEFT JOIN (
        SELECT livro_id, COUNT(*) AS ativos
        FROM emprestimos
        WHERE data_devolucao_real IS NULL
        GROUP BY livro_id
     ) a ON a.livro_id = l.id"
)->fetch_assoc();

$totalAlunos = executarConsultaDashboard(
    $conn,
    'SELECT COUNT(*) AS total FROM alunos'
)->fetch_assoc()['total'];

$resumoEmprestimos = executarConsultaDashboard(
    $conn,
    "SELECT
        COUNT(*) AS ativos,
        COALESCE(SUM(data_devolucao < CURDATE()), 0) AS atrasados
     FROM emprestimos
     WHERE data_devolucao_real IS NULL"
)->fetch_assoc();

$livrosRecentes = executarConsultaDashboard(
    $conn,
    'SELECT id, titulo, autor, genero, quantidade
     FROM livros
     ORDER BY id DESC
     LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$emprestimosRecentes = executarConsultaDashboard(
    $conn,
    "SELECT
        alunos.nome AS aluno,
        alunos.turma,
        livros.titulo AS livro,
        emprestimos.data_devolucao,
        CASE WHEN emprestimos.data_devolucao < CURDATE() THEN 'Atrasado' ELSE 'Em dia' END AS situacao
     FROM emprestimos
     INNER JOIN alunos ON alunos.id = emprestimos.aluno_id
     INNER JOIN livros ON livros.id = emprestimos.livro_id
     WHERE emprestimos.data_devolucao_real IS NULL
     ORDER BY emprestimos.data_devolucao ASC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$conn->close();

$pageTitle = 'Visão geral';
$pageDescription = 'Acompanhe o acervo, os alunos e os empréstimos em um só lugar.';
$activePage = 'dashboard';
require 'includes/header.php';
?>
<section class="row g-3" aria-label="Resumo da biblioteca">
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Títulos no acervo</div>
                    <div class="stat-value"><?= (int) $resumoLivros['titulos'] ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg>
                </span>
            </div>
            <div class="stat-note"><?= (int) $resumoLivros['exemplares'] ?> exemplares cadastrados</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Alunos cadastrados</div>
                    <div class="stat-value"><?= (int) $totalAlunos ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
            </div>
            <div class="stat-note">Leitores registrados no sistema</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Exemplares disponíveis</div>
                    <div class="stat-value"><?= (int) $resumoLivros['disponiveis'] ?></div>
                </div>
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 9.5 17 20 6.5"/></svg>
                </span>
            </div>
            <div class="stat-note">Prontos para novos empréstimos</div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="surface-card stat-card">
            <div class="stat-card-top">
                <div>
                    <div class="stat-label">Empréstimos ativos</div>
                    <div class="stat-value"><?= (int) $resumoEmprestimos['ativos'] ?></div>
                </div>
                <span class="stat-icon is-gold" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <div class="stat-note">
                <?php if ((int) $resumoEmprestimos['atrasados'] > 0): ?>
                    <span class="text-danger fw-semibold"><?= (int) $resumoEmprestimos['atrasados'] ?> em atraso</span>
                <?php else: ?>
                    Nenhum empréstimo em atraso
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<section class="dashboard-section" aria-labelledby="atalhosTitulo">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
        <div>
            <h2 class="surface-title" id="atalhosTitulo">Acesso rápido</h2>
            <p class="surface-subtitle">Vá direto para uma tarefa frequente.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <a class="quick-link" href="cadastrolivro.php">
                <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8"/></svg></span>
                <span><strong>Cadastrar livro</strong><small>Adicionar ao acervo</small></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a class="quick-link" href="cadastroaluno.php">
                <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M19 8v6m-3-3h6"/></svg></span>
                <span><strong>Cadastrar aluno</strong><small>Registrar um leitor</small></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a class="quick-link" href="emprestimos.php">
                <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2"/></svg></span>
                <span><strong>Gerenciar empréstimos</strong><small>Registrar ou receber livros</small></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a class="quick-link" href="pesquisa.php">
                <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></span>
                <span><strong>Pesquisar no acervo</strong><small>Encontrar um título</small></span>
            </a>
        </div>
    </div>
</section>

<section class="dashboard-section row g-3">
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="surface-title">Livros adicionados recentemente</h2>
                    <p class="surface-subtitle">Últimos títulos cadastrados no acervo.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="pesquisa.php">Ver acervo</a>
            </div>
            <?php if ($livrosRecentes === []): ?>
                <div class="dashboard-empty">Nenhum livro cadastrado. Adicione um título para começar o acervo.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Título</th><th>Autor</th><th class="text-end">Exemplares</th></tr></thead>
                        <tbody>
                        <?php foreach ($livrosRecentes as $livro): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($livro['autor'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end"><?= (int) $livro['quantidade'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="surface-title">Situação dos empréstimos</h2>
                    <p class="surface-subtitle">Acompanhe as próximas devoluções e atrasos.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="emprestimos.php">Ver empréstimos</a>
            </div>
            <?php if ($emprestimosRecentes === []): ?>
                <div class="dashboard-empty">Não há empréstimos ativos no momento.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Aluno / livro</th><th>Devolução</th><th>Situação</th></tr></thead>
                        <tbody>
                        <?php foreach ($emprestimosRecentes as $emprestimo): ?>
                            <tr>
                                <td>
                                    <span class="d-block fw-semibold"><?= htmlspecialchars($emprestimo['aluno'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="small text-secondary"><?= htmlspecialchars($emprestimo['livro'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($emprestimo['data_devolucao'])) ?></td>
                                <td>
                                    <?php if ($emprestimo['situacao'] === 'Atrasado'): ?>
                                        <span class="badge bg-danger">Atrasado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Em dia</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div> 
</section>
<?php require 'includes/footer.php'; ?>
