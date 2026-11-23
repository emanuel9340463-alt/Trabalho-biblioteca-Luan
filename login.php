<?php
require_once __DIR__ . '/config/seguranca.php';
iniciarSessaoSegura();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = isset($_GET['erro']) && is_string($_GET['erro']) ? $_GET['erro'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173F35">
    <title>Entrar | Biblioteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body class="app-body">
<main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <section class="surface-card w-100" style="max-width: 460px">
        <div class="mb-4">
            <p class="page-eyebrow">GESTÃO DA BIBLIOTECA</p>
            <h1 class="surface-title">Entrar</h1>
            <p class="surface-subtitle">Acesse sua conta para continuar.</p>
        </div>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php if ($erro === 'sessao'): ?>
                    O formulário expirou. Recarregue a página e tente novamente.
                <?php elseif ($erro === 'limite'): ?>
                    Houve muitas tentativas. Aguarde antes de tentar novamente.
                <?php else: ?>
                    Não foi possível autenticar. Aguarde alguns instantes e tente novamente.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form action="php/actions/login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control" type="email" name="email" id="email" maxlength="255" autocomplete="username" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="senha">Senha</label>
                <input class="form-control" type="password" name="senha" id="senha" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Entrar</button>
        </form>
    </section>
</main>
</body>
</html>
