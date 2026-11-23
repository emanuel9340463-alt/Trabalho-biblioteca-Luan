<?php
require_once __DIR__ . '/config/seguranca.php';
exigirAutenticacao();
require_once 'config/conexao.php';

// Verificar se há alunos cadastrados sem carregar toda a lista na página
$totalAlunos = 0;
$resultadoEmprestimos = null;
$livrosDisponiveis = false;
$erroCarregamento = '';

try {
    $sqlAlunos = "SELECT COUNT(*) AS total FROM alunos";
    $resultadoAlunos = $conn->query($sqlAlunos);
    $totalAlunos = (int) $resultadoAlunos->fetch_assoc()['total'];
    // Buscar empréstimos que ainda não foram devolvidos
    $sqlEmprestimos = "SELECT
                    emprestimos.id,
                    alunos.nome AS aluno,
                    alunos.turma,
                    livros.titulo AS livro,
                    emprestimos.data_emprestimo,
                    emprestimos.data_devolucao,
                    CASE
                        WHEN emprestimos.data_devolucao < CURDATE()
                        THEN 'Atrasado'
                        ELSE 'Em dia'
                    END AS situacao
                   FROM emprestimos
                   INNER JOIN alunos
                       ON emprestimos.aluno_id = alunos.id
                   INNER JOIN livros
                       ON emprestimos.livro_id = livros.id
                   WHERE emprestimos.data_devolucao_real IS NULL
                   ORDER BY emprestimos.data_devolucao ASC";

$resultadoEmprestimos = $conn->query($sqlEmprestimos);

// Verificar se há livros disponíveis sem carregar todo o catálogo na página
$sqlLivros = "SELECT COUNT(*) AS total
              FROM livros
              WHERE CAST(livros.quantidade AS SIGNED) > (
                  SELECT COUNT(*)
                  FROM emprestimos
                  WHERE emprestimos.livro_id = livros.id
                    AND emprestimos.data_devolucao_real IS NULL
              )";
    $resultadoLivros = $conn->query($sqlLivros);
    $livrosDisponiveis = (int) $resultadoLivros->fetch_assoc()['total'] > 0;
} catch (Throwable $erro) {
    error_log('Falha ao carregar página de empréstimos: ' . $erro->getMessage());
    $erroCarregamento = 'Não foi possível carregar os empréstimos. Tente novamente mais tarde.';
}
$cadastrosCompletos = $totalAlunos > 0 && $livrosDisponiveis;

// Define o título e a identificação da página usada pelo cabeçalho compartilhado.
$pageTitle = 'Empréstimos';
$pageDescription = 'Registre novas retiradas e acompanhe devoluções previstas e atrasos.';
$activePage = 'emprestimos';
?>
<?php require 'includes/header.php'; ?>
<div class="container-fluid p-0">
    <?php if ($erroCarregamento !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erroCarregamento, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php
    $mensagensResultado = [
        'sucesso' => ['success', 'Empréstimo registrado com sucesso.'],
        'devolucao_sucesso' => ['success', 'Devolução registrada com sucesso.'],
        'dados_invalidos' => ['danger', 'Os dados enviados são inválidos. Confira e tente novamente.'],
        'csrf_invalido' => ['warning', 'Sua sessão expirou. Atualize a página e tente novamente.'],
        'datas_invalidas' => ['danger', 'Confira as datas informadas para o empréstimo.'],
        'erro' => ['danger', 'Não foi possível concluir a operação. Verifique os dados e tente novamente.'],
    ];
    $resultadoAcao = isset($_GET['resultado']) && is_string($_GET['resultado'])
        ? $_GET['resultado'] : '';
    if (isset($mensagensResultado[$resultadoAcao])):
    ?>
        <div class="alert alert-<?= $mensagensResultado[$resultadoAcao][0] ?>" role="status">
            <?= htmlspecialchars($mensagensResultado[$resultadoAcao][1], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="surface-card mb-4">

        <h2 class="h4 mb-3">Novo empréstimo</h2>
        <p class="surface-subtitle mb-4">Selecione o aluno, o exemplar e a data prevista para devolução.</p>

        <?php if ($totalAlunos === 0 || !$livrosDisponiveis): ?>
            <div class="alert alert-warning" role="status">
                Antes de registrar um empréstimo, cadastre ao menos um aluno e um livro disponível.
                <?php if ($totalAlunos === 0): ?>
                    <a href="cadastroaluno.php">Cadastrar aluno</a>
                <?php endif; ?>
                <?php if (!$livrosDisponiveis): ?>
                    <a href="cadastrolivro.php">Cadastrar livro</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="php/actions/registrar_emprestimo.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label for="busca_aluno" class="form-label">Pesquisar aluno</label>
                <input
                    type="search"
                    id="busca_aluno"
                    class="form-control"
                    placeholder="Digite o nome ou a turma do aluno"
                    autocomplete="off"
                    aria-describedby="status_busca_aluno aluno_selecionado"
                    data-search-url="php/api/buscar_alunos.php"
                >
                <input type="hidden" name="aluno_id" id="aluno_id">
                <div id="status_busca_aluno" class="form-text" role="status" aria-live="polite"></div>
                <ul id="resultados_alunos" class="list-group mt-2" aria-label="Resultados da pesquisa de alunos"></ul>
                <div id="aluno_selecionado" class="alert alert-success mt-3 mb-0" role="status" hidden></div>
            </div>

            <div class="mb-3">
                <label for="busca_livro" class="form-label">Pesquisar livro</label>
                <input
                    type="search"
                    id="busca_livro"
                    class="form-control"
                    placeholder="Digite título, autor ou ISBN"
                    autocomplete="off"
                    aria-describedby="status_busca_livro livro_selecionado"
                    data-search-url="php/api/buscar_livros.php"
                >
                <input type="hidden" name="livro_id" id="livro_id">
                <div id="status_busca_livro" class="form-text" role="status" aria-live="polite"></div>
                <ul id="resultados_livros" class="list-group mt-2" aria-label="Resultados da pesquisa"></ul>
                <div id="livro_selecionado" class="alert alert-success mt-3 mb-0" role="status" hidden></div>
            </div>

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label for="data_emprestimo" class="form-label">
                        Data do empréstimo
                    </label>

                    <input
                        type="date"
                        name="data_emprestimo"
                        id="data_emprestimo"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label for="data_devolucao" class="form-label">
                        Devolução prevista
                    </label>

                    <input
                        type="date"
                        name="data_devolucao"
                        id="data_devolucao"
                        class="form-control"
                        value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                        required
                    >
                </div>

            </div>

            <button type="submit" class="btn btn-primary" <?= !$cadastrosCompletos ? 'disabled' : '' ?>>
                Registrar empréstimo
            </button>

        </form>

    </section>
    
    <section class="surface-card">

        <h2 class="h4 mb-3">Empréstimos ativos</h2>

        <?php if ($erroCarregamento !== ''): ?>
            <p class="text-secondary mb-0">A lista de empréstimos está temporariamente indisponível.</p>
        <?php elseif ($resultadoEmprestimos && $resultadoEmprestimos->num_rows > 0): ?>

            <div class="table-responsive">

                <table class="table table-striped table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Turma</th>
                            <th>Livro</th>
                            <th>Empréstimo</th>
                            <th>Devolução prevista</th>
                            <th>Situação</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($emprestimo = $resultadoEmprestimos->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars($emprestimo['aluno']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($emprestimo['turma']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($emprestimo['livro']) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($emprestimo['data_emprestimo'])) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($emprestimo['data_devolucao'])) ?>
                                </td>

                                <td>
                                    <?php if ($emprestimo['situacao'] === 'Atrasado'): ?>
                                        <span class="badge bg-danger">Atrasado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Em dia</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form action="php/actions/registrar_devolucao.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input
                                            type="hidden"
                                            name="emprestimo_id"
                                            value="<?= (int) $emprestimo['id'] ?>"
                                        >

                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Devolver
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="text-secondary mb-0">
                Não existem empréstimos ativos no momento.
            </p>

        <?php endif; ?>

    </section>

    </div>
<script src="js/emprestimos.js?v=2"></script>
<?php require 'includes/footer.php'; ?>