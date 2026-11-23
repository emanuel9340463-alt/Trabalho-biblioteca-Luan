<?php
require_once __DIR__ . '/../config/seguranca.php';
exigirAutenticacao();
$tokenCabecalho = tokenCsrf();

$pageTitle = $pageTitle ?? 'Biblioteca';
$pageDescription = $pageDescription ?? '';
$activePage = $activePage ?? '';
$pageStyles = $pageStyles ?? [];

$navigationItems = [
    ['id' => 'dashboard', 'label' => 'Visão geral', 'href' => 'index.php', 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
    ['id' => 'livros', 'label' => 'Cadastrar livros', 'href' => 'cadastrolivro.php', 'icon' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/>'],
    [
        'id' => 'alunos',
        'label' => 'Alunos',
        'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'children' => [
            ['id' => 'alunos', 'label' => 'Cadastrar Aluno', 'href' => 'cadastroaluno.php'],
            ['id' => 'pesquisar_alunos', 'label' => 'Pesquisar Alunos', 'href' => 'pesquisar_alunos.php'],
            ['id' => 'promover_alunos', 'label' => 'Promover Alunos', 'href' => 'promover_alunos.php'],
        ],
    ],
    ['id' => 'pesquisa', 'label' => 'Acervo e pesquisa', 'href' => 'pesquisa.php', 'icon' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>'],
    ['id' => 'emprestimos', 'label' => 'Empréstimos', 'href' => 'emprestimos.php', 'icon' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/>'],
    ['id' => 'historico', 'label' => 'Histórico de empréstimos', 'href' => 'historico.php', 'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M12 14v3l2 1"/>'],
];
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173F35">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Biblioteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=3">
    <?php foreach ($pageStyles as $pageStyle): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($pageStyle, ENT_QUOTES, 'UTF-8') ?>?v=3">
    <?php endforeach; ?>
</head>
<body class="app-body">
<div class="app-layout">
    <aside class="app-sidebar" id="appSidebar" aria-label="Navegação principal">
        <a class="sidebar-brand" href="index.php" aria-label="Biblioteca, visão geral">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg>
            </span>
            <span class="sidebar-label brand-name">Biblioteca</span>
        </a>

        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Recolher menu" aria-expanded="true" title="Recolher menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 18-6-6 6-6"/></svg>
        </button>

        <p class="sidebar-section sidebar-label">MENU</p>
        <nav class="sidebar-nav">
            <?php foreach ($navigationItems as $item): ?>
                <?php if (isset($item['children'])): ?>
                    <?php
                    $submenuAtivo = false;
                    foreach ($item['children'] as $child) {
                        if ($activePage === $child['id']) {
                            $submenuAtivo = true;
                            break;
                        }
                    }
                    ?>
                    <div class="sidebar-menu-group">
                        <button
                            class="sidebar-link sidebar-menu-toggle<?= $submenuAtivo ? ' is-active' : '' ?>"
                            type="button"
                            data-submenu-toggle
                            aria-controls="studentSubmenu"
                            aria-expanded="<?= $submenuAtivo ? 'true' : 'false' ?>"
                            title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $item['icon'] ?></svg>
                            <span class="sidebar-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <svg class="sidebar-menu-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="sidebar-submenu" id="studentSubmenu" <?= $submenuAtivo ? '' : 'hidden' ?>>
                            <?php foreach ($item['children'] as $child): ?>
                                <a
                                    class="sidebar-submenu-link<?= $activePage === $child['id'] ? ' is-active' : '' ?>"
                                    href="<?= htmlspecialchars($child['href'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $activePage === $child['id'] ? 'aria-current="page"' : '' ?>
                                ><?= htmlspecialchars($child['label'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="sidebar-link<?= $activePage === $item['id'] ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $activePage === $item['id'] ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $item['icon'] ?></svg>
                        <span class="sidebar-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <span class="sidebar-status-dot" aria-hidden="true"></span>
            <span class="sidebar-label">Sistema da biblioteca</span>
        </div>
    </aside>

    <button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Fechar menu"></button>

    <div class="app-main">
        <header class="app-topbar">
            <button class="mobile-menu-toggle" type="button" data-mobile-menu-toggle aria-label="Abrir menu" aria-expanded="false" aria-controls="appSidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="topbar-context">
                <span class="topbar-kicker">GESTÃO DA BIBLIOTECA</span>
                <span class="topbar-page"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="topbar-meta">
                <span class="system-status"><span aria-hidden="true"></span> Sistema ativo</span>
                <time datetime="<?= date('Y-m-d') ?>"><?= date('d/m/Y') ?></time>
                <form action="php/actions/logout.php" method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenCabecalho, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Sair</button>
                </form>
            </div>
        </header>

        <main class="app-content">
            <div class="page-heading">
                <div>
                    <p class="page-eyebrow">BIBLIOTECA • ADMINISTRAÇÃO</p>
                    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($pageDescription !== ''): ?>
                        <p class="page-description"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>
