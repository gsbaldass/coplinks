<?php

declare(strict_types=1);

namespace Bio;

final class AdminView
{
    private const STATUS = ['draft' => 'Rascunho', 'published' => 'Publicada', 'inactive' => 'Desativada'];

    public static function templateThemes(): array
    {
        $base = ['background' => '#f5f1eb', 'accent' => '#147d69', 'text' => '#233b35', 'cardBackground' => '#ffffff', 'font' => 'sans', 'radius' => 'rounded', 'style' => 'solid', 'buttonStyle' => 'filled'];
        return [
            'natural' => $base,
            'forest' => array_replace($base, ['background' => '#153d33', 'accent' => '#d8eabd', 'text' => '#f1f5e9', 'cardBackground' => '#285044', 'style' => 'gradient']),
            'rose' => array_replace($base, ['background' => '#f6e7e3', 'accent' => '#a5544b', 'text' => '#53382f', 'cardBackground' => '#fff9f6', 'font' => 'serif', 'radius' => 'soft']),
            'night' => array_replace($base, ['background' => '#211f33', 'accent' => '#c2b3ff', 'text' => '#f1edff', 'cardBackground' => '#343045', 'style' => 'gradient']),
            'minimal' => array_replace($base, ['background' => '#fafafa', 'accent' => '#262626', 'text' => '#262626', 'radius' => 'square', 'buttonStyle' => 'outline']),
            'sunny' => array_replace($base, ['background' => '#fff2cb', 'accent' => '#956229', 'text' => '#574027', 'cardBackground' => '#fff9e7', 'style' => 'dotted']),
        ];
    }

    private static function templateNames(): array
    {
        return ['natural' => 'Natural', 'forest' => 'Floresta', 'rose' => 'Acolhimento', 'night' => 'Noturno', 'minimal' => 'Essencial', 'sunny' => 'Luz do dia'];
    }

    private function head(string $title, string $bodyClass = ''): void
    {
        ?>
        <!doctype html>
        <html lang="pt-BR">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?= e($title) ?> · CopLinks</title>
            <meta name="description" content="Um espaço para cada comunidade. Crie e gerencie suas páginas de links com CopLinks.">
            <meta name="robots" content="noindex,nofollow">
            <meta name="theme-color" content="#147d69">
            <link rel="icon" type="image/svg+xml" href="/assets/mark.svg">
            <link rel="stylesheet" href="/assets/admin.css">
        </head>
        <body class="<?= e($bodyClass) ?>">
        <a class="skip-link" href="#main">Pular para o conteúdo</a>
        <?php
    }

    private function wordmark(): void
    {
        ?><span class="wordmark"><span class="brand-mark"><?= icon('link', 27) ?></span><span>CopLinks<span class="brand-dot">.</span></span></span><?php
    }

    private function start(array $user, string $section, string $title): void
    {
        $this->head($title, 'admin-body');
        ?>
        <div class="app-shell">
            <aside class="sidebar" aria-label="Navegação do painel">
                <a href="/admin" class="brand" aria-label="CopLinks, painel de links"><?php $this->wordmark(); ?></a>
                <div class="workspace-card"><span class="workspace-avatar">CO</span><div><strong>Convenção Oeste Paulista</strong><span>Painel de links</span></div></div>
                <p class="nav-label">SEU ESPAÇO</p>
                <nav class="main-nav">
                    <?php foreach (['pages' => ['/admin', 'grid', 'Minhas páginas'], 'templates' => ['/admin/templates', 'palette', 'Modelos'], 'analytics' => ['/admin/analytics', 'chart', 'Estatísticas'], 'settings' => ['/admin/settings', 'settings', 'Configurações']] as $key => [$href, $symbol, $label]): ?>
                    <a href="<?= e($href) ?>" class="<?= $key === $section ? 'active' : '' ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span><?= $key === $section ? '<span class="nav-dot"></span>' : '' ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-bottom">
                    <div class="side-note"><?= icon('spark', 24) ?><strong>Feito para conectar.</strong><p>Um espaço para cada comunidade. Uma identidade para cada página.</p><a href="/admin/new">Criar uma conexão <?= icon('arrow', 16) ?></a></div>
                    <div class="sidebar-footer"><span class="small-dot"></span> PHP orientado a objetos <span>v1.0</span></div>
                </div>
            </aside>
            <div class="main-column">
                <header class="topbar"><div class="breadcrumb">Workspace <?= icon('chevron', 14) ?> <span><?= e($title) ?></span></div><div class="account"><span class="account-label">Administrador</span><span class="account-avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?: 'CO', 0, 2))) ?></span><form method="post" action="/auth/logout"><?php $this->csrf(); ?><button class="icon-button" title="Sair" aria-label="Sair do painel"><?= icon('logout', 18) ?></button></form></div></header>
                <main id="main" class="workspace-main">
                <?php $this->notice(); ?>
        <?php
    }

    private function end(): void
    {
        ?><footer class="main-footer"><span>Conexões que aproximam.</span><span>CopLinks · Seu projeto, seus links.</span></footer></main></div></div></body></html><?php
    }

    private function csrf(): void
    {
        ?><input type="hidden" name="_csrf" value="<?= e(Auth::csrf()) ?>"><?php
    }

    private function notice(): void
    {
        $messages = [
            'saved' => 'Alterações salvas. A prévia já foi atualizada.',
            'created' => 'Página criada como rascunho. Agora ela está pronta para ganhar seus links.',
            'duplicated' => 'Página duplicada como rascunho. Personalize a cópia antes de publicar.',
            'deleted' => 'Página excluída.',
            'published' => 'Página publicada. Seu link já pode ser compartilhado.',
            'draft' => 'Página voltou para rascunho e não está mais acessível ao público.',
            'inactive' => 'Página desativada. Os dados continuam salvos no painel.',
            'card-added' => 'Card criado. Preencha o conteúdo e ative quando estiver pronto.',
        ];
        $message = $messages[(string) ($_GET['notice'] ?? '')] ?? null;
        if ($message) {
            ?><div class="notice" role="status"><?= icon('check', 18) ?><?= e($message) ?></div><?php
        }
    }

    public function landing(?string $message = null): void
    {
        $this->head('Todas as suas conexões, em um só lugar', 'landing-body');
        ?>
        <header class="landing-nav"><a class="brand" href="/" aria-label="CopLinks, início"><?php $this->wordmark(); ?></a><span class="landing-project">Convenção Oeste Paulista <span class="nav-separator">/</span> Painel de links</span><a class="button button-light" href="/login" target="_top">Acessar painel <?= icon('arrow', 17) ?></a></header>
        <main id="main" class="landing-main">
            <section class="landing-copy"><div class="eyebrow"><span class="small-dot"></span> UM ESPAÇO PARA CADA COMUNIDADE</div><h1>Seus links.<br>Suas pessoas.<br><em>Conectando comunidades.</em></h1><p class="landing-description">Reúna o que importa. Crie páginas únicas, personalize cada detalhe e conecte sua comunidade em um só lugar.</p>
                <?php if ($message): ?><div class="notice login-notice" role="alert"><?= icon('shield', 18) ?><?= e($message) ?></div><?php endif; ?>
                <a class="button button-primary button-large" href="/login" target="_top">Entrar no meu painel <?= icon('arrow', 20) ?></a><p class="login-hint"><?= icon('shield', 15) ?> Acesso com senha administrativa</p>
                <div class="landing-proof"><div><strong>36</strong><span>páginas para começar</span></div><div><strong>100%</strong><span>do seu jeito</span></div><div><strong>1</strong><span>painel centralizado</span></div></div>
            </section>
            <section class="landing-visual" aria-label="Exemplo visual de uma página de links">
                <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div><span class="floating-tag tag-top"><?= icon('palette', 17) ?> Sua identidade, seus links</span>
                <div class="sample-phone"><div class="sample-status"><span>9:41</span><span>● ● ●</span></div><div class="sample-profile"><span class="sample-avatar"><?= icon('heart', 34) ?></span><span class="sample-label">SUA COMUNIDADE</span><h2>Há lugar<br>para todos.</h2><p>Pessoas, propósito e possibilidades.<br>Tudo começa com uma conexão.</p></div><div class="sample-card sample-featured"><?= icon('heart', 21) ?><div><strong>Faça parte</strong><span>Encontre a sua comunidade</span></div><?= icon('arrow', 18) ?></div><div class="sample-card"><?= icon('calendar', 21) ?><div><strong>Nossos encontros</strong><span>Momentos para estar perto</span></div><?= icon('external', 17) ?></div><div class="sample-card"><?= icon('globe', 21) ?><div><strong>Conheça nosso projeto</strong></div><?= icon('external', 17) ?></div><div class="sample-socials"><?= icon('instagram', 19) ?><?= icon('youtube', 21) ?><?= icon('whatsapp', 20) ?></div><div class="sample-brand">feito com <strong>CopLinks.</strong></div></div>
                <span class="floating-tag tag-bottom"><span class="tag-check"><?= icon('check', 15) ?></span> Um link. Muitas possibilidades.</span><span class="example-caption">EXEMPLO VISUAL · CONTEÚDO PERSONALIZÁVEL</span>
            </section>
        </main><section class="landing-features"><div><?= icon('grid', 23) ?><strong>Todas as páginas juntas</strong><span>Crie, organize e publique em um painel.</span></div><div><?= icon('palette', 23) ?><strong>Liberdade para personalizar</strong><span>Cores, imagens e cards com a sua identidade.</span></div><div><?= icon('phone', 23) ?><strong>Feito para o celular</strong><span>Um link bonito, onde sua comunidade estiver.</span></div></section><footer class="landing-footer"><span>CopLinks · Conexões que aproximam.</span><span>PHP orientado a objetos · sem frameworks</span></footer></body></html>
        <?php
    }

    public function loginForm(string $error = ''): void
    {
        $this->head('Entrar no painel', 'landing-body');
        $messages = [
            'invalid' => 'Senha incorreta. Verifique e tente novamente.',
            'empty'   => 'Informe a senha para continuar.',
        ];
        $errorMessage = $messages[$error] ?? null;
        ?>
        <header class="landing-nav"><a class="brand" href="/" aria-label="CopLinks, início"><?php $this->wordmark(); ?></a><span class="landing-project">Convenção Oeste Paulista <span class="nav-separator">/</span> Painel de links</span></header>
        <main id="main" class="landing-main login-main">
            <section class="login-box">
                <div class="eyebrow"><span class="small-dot"></span> ACESSO ADMINISTRATIVO</div>
                <h1>Entrar no painel<span class="heading-dot">.</span></h1>
                <p>Insira a senha de administrador para gerenciar as páginas.</p>
                <?php if ($errorMessage): ?>
                <div class="notice login-notice" role="alert"><?= icon('shield', 18) ?><?= e($errorMessage) ?></div>
                <?php endif; ?>
                <form method="post" action="/auth/login" class="login-form">
                    <label class="field">
                        <span>Senha</span>
                        <input type="password" name="password" required autofocus autocomplete="current-password" placeholder="Sua senha administrativa">
                    </label>
                    <button class="button button-primary button-large" type="submit">Entrar no painel <?= icon('arrow', 18) ?></button>
                </form>
                <p class="login-hint"><?= icon('shield', 15) ?> Sessão segura com HttpOnly cookie</p>
                <a href="/" class="back-link">← Voltar para a página inicial</a>
            </section>
        </main>
        <footer class="landing-footer"><span>CopLinks · Conexões que aproximam.</span><span>PHP orientado a objetos · sem frameworks</span></footer>
        </body></html>
        <?php
    }

    public function dashboard(array $user, array $pages, array $stats, string $q, string $status, string $layout): void
    {
        $this->start($user, 'pages', 'Minhas páginas');
        $totalFound = count($pages);
        $numPages = max(1, (int) ceil($totalFound / 12));
        $current = max(1, min($numPages, (int) ($_GET['page'] ?? 1)));
        $visible = array_slice($pages, ($current - 1) * 12, 12);
        ?>
        <div class="page-heading"><div><div class="eyebrow">CONECTE. PERSONALIZE. COMPARTILHE.</div><h1>Minhas páginas<span class="heading-dot">.</span></h1><p>Um espaço para cada comunidade, todos no mesmo lugar.</p></div><a class="button button-primary" href="/admin/new"><?= icon('plus', 18) ?> Nova página</a></div>
        <section class="project-banner"><div><span class="banner-label">SEU PROJETO, SUAS CONEXÕES</span><h2>Uma identidade para cada página.</h2><p>As 36 páginas iniciais estão em rascunho. Dê um nome a cada uma e faça os links acontecerem.</p><a href="/admin/templates">Encontre um modelo para começar <?= icon('arrow', 16) ?></a></div><div class="banner-symbol" aria-hidden="true"><span class="symbol-piece piece-one"></span><span class="symbol-piece piece-two"></span><span class="symbol-piece piece-three"></span><span class="symbol-piece piece-four"></span><span class="symbol-center"><?= icon('link', 39) ?></span></div></section>
        <section class="stats-row" aria-label="Resumo do projeto"><?php foreach ([['total', 'Total de páginas', 'grid', 'No seu projeto'], ['published', 'Publicadas', 'globe', 'Prontas para compartilhar'], ['clicks', 'Cliques nos links', 'arrow', 'Desde o início'], ['draft', 'Em rascunho', 'edit', 'Esperando seu toque']] as [$key, $label, $symbol, $hint]): ?><div class="stat-box"><div class="stat-top"><span><?= e($label) ?></span><span class="stat-icon"><?= icon($symbol, 17) ?></span></div><strong><?= number_format((int) $stats[$key], 0, ',', '.') ?></strong><span class="stat-hint"><?= e($hint) ?></span></div><?php endforeach; ?></section>
        <div class="catalog-header"><div class="catalog-tabs"><?php foreach (['all' => 'Todas', 'published' => 'Publicadas', 'draft' => 'Rascunhos', 'inactive' => 'Desativadas'] as $value => $label): ?><a href="/admin?<?= e(http_build_query(['status' => $value, 'q' => $q, 'view' => $layout])) ?>" class="<?= $status === $value ? 'active' : '' ?>"><?= e($label) ?><?= $value === 'all' ? '<span>' . e($stats['total']) . '</span>' : '' ?></a><?php endforeach; ?></div><div class="catalog-tools"><form class="search-box" method="get" action="/admin"><?= icon('search', 18) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar uma página..." aria-label="Buscar página por nome ou URL"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="view" value="<?= e($layout) ?>"><button class="search-submit" aria-label="Buscar"><?= icon('arrow', 15) ?></button></form><div class="view-toggle"><?php foreach (['grid' => 'Grade', 'list' => 'Lista'] as $value => $label): ?><a href="/admin?<?= e(http_build_query(['status' => $status, 'q' => $q, 'view' => $value])) ?>" class="<?= $layout === $value ? 'active' : '' ?>" aria-label="Visualizar em <?= e($label) ?>"><?= icon($value, 17) ?></a><?php endforeach; ?></div></div></div>
        <div class="result-count"><?= e($totalFound) ?> <?= $totalFound === 1 ? 'página' : 'páginas' ?><?= $q !== '' ? ' para “' . e($q) . '”' : ' no seu espaço' ?><?php if ($q !== ''): ?><a href="/admin">Limpar busca</a><?php endif; ?></div>
        <?php if (!$visible): ?><div class="empty-state"><span><?= icon('search', 32) ?></span><h2>Nenhuma página por aqui.</h2><p>Tente outro nome ou crie uma nova conexão.</p><a class="button button-light" href="/admin/new"><?= icon('plus', 17) ?> Criar página</a></div><?php endif; ?>
        <section class="pages-catalog <?= $layout === 'list' ? 'is-list' : '' ?>" aria-label="Páginas de links">
            <?php foreach ($visible as $page): ?>
            <article class="page-tile"><a class="tile-preview" href="/admin/pages/<?= e($page['id']) ?>" aria-label="Editar <?= e($page['name']) ?>" style="--tile-bg:<?= e($page['theme']['background']) ?>;--tile-accent:<?= e($page['theme']['accent']) ?>;--tile-text:<?= e($page['theme']['text']) ?>;--tile-card:<?= e($page['theme']['cardBackground']) ?>"><div class="tile-preview-inner"><?php if ($page['avatar']): ?><img class="tile-avatar" src="<?= e($page['avatar']) ?>" alt="" loading="lazy"><?php else: ?><span class="tile-avatar"><?= e(mb_strtoupper(mb_substr($page['title'] ?: $page['name'], 0, 1))) ?></span><?php endif; ?><strong><?= e($page['title'] ?: $page['name']) ?></strong><div class="tile-mini-links"><?php if (!$page['cards']): ?><span class="mini-empty">Seus links começam aqui</span><?php else: foreach (array_slice($page['cards'], 0, 2) as $card): ?><span><?= e($card['title']) ?><?= icon('arrow', 10) ?></span><?php endforeach; endif; ?></div></div><span class="tile-edit"><?= icon('edit', 15) ?> Editar página</span></a><div class="tile-content"><div class="tile-title-row"><h3><a href="/admin/pages/<?= e($page['id']) ?>"><?= e($page['name']) ?></a></h3><span class="status-badge <?= e($page['status']) ?>"><span></span><?= e(self::STATUS[$page['status']]) ?></span></div><span class="tile-url"><?= icon('link', 13) ?> /<?= e($page['slug']) ?></span><div class="tile-footer"><span><?= count($page['cards']) ?> <?= count($page['cards']) === 1 ? 'card' : 'cards' ?></span><a href="/admin/pages/<?= e($page['id']) ?>">Personalizar <?= icon('arrow', 15) ?></a></div></div></article>
            <?php endforeach; ?>
        </section>
        <?php if ($numPages > 1): ?><nav class="pagination" aria-label="Paginação"><span>Página <?= $current ?> de <?= $numPages ?></span><div><?php for ($i = 1; $i <= $numPages; $i++): ?><a href="/admin?<?= e(http_build_query(['status' => $status, 'q' => $q, 'view' => $layout, 'page' => $i])) ?>" class="<?= $current === $i ? 'active' : '' ?>"<?= $current === $i ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></div></nav><?php endif; ?>
        <?php $this->end();
    }

    public function newPage(array $user, string $template): void
    {
        $this->start($user, 'pages', 'Nova página');
        $names = self::templateNames();
        $template = isset($names[$template]) ? $template : 'natural';
        ?>
        <a class="back-link" href="/admin">← Voltar para minhas páginas</a><div class="page-heading"><div><div class="eyebrow">UMA NOVA CONEXÃO</div><h1>Vamos criar uma página<span class="heading-dot">.</span></h1><p>Comece com a identidade. Os links vêm logo depois.</p></div></div>
        <form class="create-layout" method="post" action="/admin/new"><?php $this->csrf(); ?><section class="panel"><div class="panel-title"><?= icon('edit', 20) ?><div><h2>Sobre a página</h2><p>Você pode mudar tudo isso depois.</p></div></div><?php $this->field('Nome da página', 'name', '', 'text', 'Ex.: Comunidade de Presidente Prudente', true, 120); ?><?php $this->field('Endereço da página', 'slug', '', 'text', 'Ex.: presidente-prudente', true, 64); ?><p class="field-help">Use letras minúsculas, números e hífens. O link será /seu-endereco.</p><label class="field"><span>Descrição <small>opcional</small></span><textarea name="description" rows="3" maxlength="1000" placeholder="Uma frase para apresentar sua comunidade."></textarea></label><div class="callout"><?= icon('shield', 18) ?><p>A nova página começa em <strong>rascunho</strong>. Só você poderá vê-la até decidir publicar.</p></div></section><section class="panel"><div class="panel-title"><?= icon('palette', 20) ?><div><h2>Escolha um ponto de partida</h2><p>Uma identidade, infinitas possibilidades.</p></div></div><div class="theme-choices"><?php foreach (self::templateThemes() as $key => $theme): ?><label class="theme-option"><input type="radio" name="template" value="<?= e($key) ?>"<?= checked($key === $template) ?>><span class="theme-swatch" style="--theme-bg:<?= e($theme['background']) ?>;--theme-fg:<?= e($theme['accent']) ?>;--theme-card:<?= e($theme['cardBackground']) ?>"><i></i><b></b><b></b></span><strong><?= e($names[$key]) ?></strong></label><?php endforeach; ?></div><div class="form-actions"><a class="button button-light" href="/admin">Cancelar</a><button class="button button-primary">Criar minha página <?= icon('arrow', 18) ?></button></div></section></form>
        <?php $this->end();
    }

    public function editor(array $user, array $page, string $tab, ?array $card, array $stats): void
    {
        $this->start($user, 'pages', 'Editar página');
        $action = '/admin/pages/' . $page['id'];
        $previewEndpoint = $action . '/live-preview';
        ?>
        <script src="/assets/admin-live-preview.js" defer></script>
        <a class="back-link" href="/admin">← Minhas páginas</a><div class="page-heading editor-heading"><div><div class="editor-title"><h1><?= e($page['name']) ?></h1><span class="status-badge <?= e($page['status']) ?>"><span></span><?= e(self::STATUS[$page['status']]) ?></span></div><p class="edit-url"><?= icon('link', 15) ?> /<?= e($page['slug']) ?> <?php if ($page['status'] === 'published'): ?><a href="/<?= e($page['slug']) ?>" target="_blank" rel="noopener">Abrir página <?= icon('external', 14) ?></a><?php endif; ?></p></div><div class="editor-publish"><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="duplicate"><button class="button button-light" title="Duplicar página"><?= icon('copy', 17) ?><span>Duplicar</span></button></form><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $page['status'] === 'published' ? 'inactive' : 'published' ?>"><button class="button <?= $page['status'] === 'published' ? 'button-light' : 'button-primary' ?>"><?= icon($page['status'] === 'published' ? 'eye' : 'globe', 18) ?><?= $page['status'] === 'published' ? 'Desativar' : 'Publicar página' ?></button></form></div></div>
        <nav class="editor-tabs" aria-label="Seções da edição"><?php foreach (['links' => ['link', 'Links e cards'], 'details' => ['edit', 'Perfil da página'], 'appearance' => ['palette', 'Aparência'], 'socials' => ['globe', 'Redes sociais']] as $key => [$symbol, $label]): ?><a href="<?= e($action) ?>?tab=<?= e($key) ?>" class="<?= $key === $tab ? 'active' : '' ?>"><?= icon($symbol, 18) ?><?= e($label) ?></a><?php endforeach; ?></nav>
        <div class="editor-layout"><div class="editor-controls">
            <div class="callout live-preview-note"><?= icon('eye', 18) ?><p>A prévia é atualizada automaticamente enquanto você edita. As alterações só serão aplicadas ao site público depois de salvar.</p></div><?php if ($page['status'] === 'published'): ?><div class="callout live-callout"><?= icon('globe', 18) ?><p>Esta página está publicada; alterações ainda não salvas não mudam o link público.</p></div><?php endif; ?>
            <?php if ($tab === 'links'): ?>
            <div class="section-heading"><div><h2>Links que conectam</h2><p>Adicione cards, dê destaque e organize do seu jeito.</p></div><span class="count-pill"><?= count($page['cards']) ?> cards</span></div>
            <div class="add-card-options"><?php foreach (['link' => ['link', 'Link'], 'image' => ['image', 'Imagem'], 'photo' => ['image', 'Foto'], 'heading' => ['text', 'Seção'], 'text' => ['book', 'Texto'], 'maps' => ['map', 'Mapa']] as $type => [$symbol, $label]): ?><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="card-add"><input type="hidden" name="type" value="<?= e($type) ?>"><button><?= icon($symbol, 20) ?><span><?= e($label) ?></span><?= icon('plus', 14) ?></button></form><?php endforeach; ?></div>
            <?php if ($card): $this->cardEditor($action, $card); endif; ?>
            <?php if (!$page['cards']): ?><div class="empty-state cards-empty"><span><?= icon('link', 32) ?></span><h3>A primeira conexão começa aqui.</h3><p>Escolha um tipo de card acima para adicionar o seu primeiro conteúdo.</p><span class="muted">Links, imagens, títulos de seção e mensagens.</span></div><?php else: ?><div class="card-stack"><?php foreach ($page['cards'] as $index => $item): ?><article class="editable-card <?= !$item['enabled'] ? 'disabled-card' : '' ?>"><div class="card-reorder"><?php foreach (['up' => 'Mover para cima', 'down' => 'Mover para baixo'] as $direction => $label): ?><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="card-move"><input type="hidden" name="card_id" value="<?= e($item['id']) ?>"><input type="hidden" name="direction" value="<?= e($direction) ?>"><button class="icon-button" aria-label="<?= e($label . ': ' . $item['title']) ?>"<?= ($direction === 'up' && $index === 0) || ($direction === 'down' && $index === count($page['cards']) - 1) ? ' disabled' : '' ?>><?= icon($direction, 15) ?></button></form><?php endforeach; ?></div><span class="editable-card-icon" style="color:<?= e($item['textColor']) ?>;background:<?= e($item['color']) ?>"><?= icon($item['type'] === 'image' ? 'image' : ($item['type'] === 'maps' ? 'map' : ($item['type'] === 'photo' ? 'image' : $item['icon'])), 21) ?></span><div class="editable-card-content"><a href="<?= e($action) ?>?card=<?= e($item['id']) ?>"><strong><?= e($item['title']) ?></strong></a><span><?= e($item['url'] ?: (['heading' => 'Título de seção', 'text' => 'Mensagem', 'image' => 'Adicione um destino', 'link' => 'Adicione um destino', 'maps' => 'Adicione um mapa', 'photo' => 'Adicione uma imagem'][$item['type']] ?? 'Adicione um destino')) ?></span><small><?= $item['enabled'] ? 'Visível' : 'Oculto' ?><?= $item['highlight'] ? ' · Em destaque' : '' ?><?= $item['badge'] ? ' · ' . e($item['badge']) : '' ?></small></div><div class="card-item-actions"><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="card-toggle"><input type="hidden" name="card_id" value="<?= e($item['id']) ?>"><button class="visibility-button <?= $item['enabled'] ? 'on' : '' ?>" aria-label="<?= e(($item['enabled'] ? 'Ocultar' : 'Mostrar') . ' card ' . $item['title']) ?>" title="<?= $item['enabled'] ? 'Ocultar card' : 'Mostrar card' ?>"><span></span></button></form><a class="icon-button" href="<?= e($action) ?>?card=<?= e($item['id']) ?>" aria-label="<?= e('Editar ' . $item['title']) ?>"><?= icon('edit', 17) ?></a><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="card-duplicate"><input type="hidden" name="card_id" value="<?= e($item['id']) ?>"><button class="icon-button" title="Duplicar card" aria-label="<?= e('Duplicar ' . $item['title']) ?>"><?= icon('copy', 17) ?></button></form><a class="icon-button danger-text" href="<?= e($action) ?>/card-delete?card=<?= e($item['id']) ?>" aria-label="<?= e('Excluir ' . $item['title']) ?>"><?= icon('trash', 17) ?></a></div></article><?php endforeach; ?></div><p class="field-help"><?= icon('up', 14) ?> Use as setas para mudar a ordem. Os cards ocultos ficam salvos, mas não aparecem na página.</p><?php endif; ?>
            <?php elseif ($tab === 'details'): ?>
            <form class="panel" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-live-preview-form data-live-preview-endpoint="<?= e($previewEndpoint) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="details"><div class="panel-title"><?= icon('edit', 20) ?><div><h2>O rosto da sua página</h2><p>Uma identidade que sua comunidade reconhece.</p></div></div><div class="profile-upload"><?php if ($page['avatar']): ?><img src="<?= e($page['avatar']) ?>" alt="Foto atual da página"><?php else: ?><span><?= e(mb_strtoupper(mb_substr($page['name'], 0, 1))) ?></span><?php endif; ?><div><label class="field"><span>Enviar foto ou logotipo</span><input type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp"></label><small>PNG, JPG ou WebP. Até 2 MB.</small></div></div><?php $this->field('Ou usar uma imagem por URL', 'avatar', $page['avatar'], 'text', 'https://...'); ?><?php if ($page['avatar']): ?><label class="check-field"><input type="checkbox" name="remove_avatar"> Remover foto atual</label><?php endif; ?><?php $this->field('Nome interno da página', 'name', $page['name'], 'text', '', true, 120); ?><?php $this->field('Título que aparece para o público', 'title', $page['title'], 'text', '', true, 120); ?><label class="field"><span>Descrição</span><textarea name="description" rows="4" maxlength="1000"><?= e($page['description']) ?></textarea></label><?php $this->field('Endereço da página', 'slug', $page['slug'], 'text', 'nome-da-pagina', true, 64); ?><p class="field-help">Alterar o endereço modifica seu link público. Atualize a bio e os materiais que já compartilhou.</p><div class="form-actions"><button class="button button-primary"><?= icon('check', 18) ?> Salvar perfil</button></div></form>
            <section class="panel danger-zone"><div><h3>Gerenciar esta página</h3><p>Desativar preserva os dados. Excluir remove a página e seus cards.</p></div><div class="form-actions"><?php if ($page['status'] !== 'draft'): ?><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="draft"><button class="button button-light">Voltar para rascunho</button></form><?php endif; ?><a class="button button-danger" href="<?= e($action) ?>/delete"><?= icon('trash', 17) ?> Excluir página</a></div></section>
            <?php elseif ($tab === 'appearance'): ?>
            <section class="panel"><div class="panel-title"><?= icon('palette', 21) ?><div><h2>Sua identidade visual</h2><p>Comece com um tema ou personalize cada detalhe.</p></div></div><div class="preset-inline"><?php foreach (self::templateThemes() as $key => $theme): ?><form method="post" action="<?= e($action) ?>" data-live-preview-form data-live-preview-endpoint="<?= e($action) ?>/live-preview" data-live-preview-submit><?php $this->csrf(); ?><input type="hidden" name="action" value="appearance"><input type="hidden" name="themePreset" value="<?= e($key) ?>"><button class="preset-button"><span style="background:<?= e($theme['background']) ?>;color:<?= e($theme['accent']) ?>"><?= icon('link', 20) ?></span><?= e(self::templateNames()[$key]) ?></button></form><?php endforeach; ?></div><p class="field-help">Aplicar um tema troca as configurações visuais e preserva o conteúdo.</p></section>
            <form class="panel" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-live-preview-form data-live-preview-endpoint="<?= e($previewEndpoint) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="appearance">
            <h3 class="subsection-title">Cores que representam você</h3>
            <div class="color-fields"><?php foreach (['background' => 'Fundo da página', 'accent' => 'Cor de destaque', 'text' => 'Textos da página', 'cardBackground' => 'Fundo dos cards'] as $key => $label): ?><label><input type="color" name="<?= e($key) ?>" value="<?= e($page['theme'][$key]) ?>"><span><?= e($label) ?><small><?= e($page['theme'][$key]) ?></small></span></label><?php endforeach; ?></div>

            <h3 class="subsection-title" style="margin-top:10px">Fundo da página</h3>
            <div class="field-grid"><?php $this->selectField('Tipo de fundo', 'backgroundType', (string) ($page['theme']['backgroundType'] ?? 'solid'), ['solid' => 'Cor sólida (usa a cor de fundo acima)', 'gradient' => 'Gradiente personalizado', 'image' => 'Imagem de fundo']); ?></div>
            <div class="bg-extra" id="bg-gradient-field" style="<?= ($page['theme']['backgroundType'] ?? 'solid') !== 'gradient' ? 'display:none' : '' ?>">
                <label class="field"><span>Gradiente CSS <small>ex: linear-gradient(135deg, #f5f1eb 0%, #d8eabd 100%)</small></span><input type="text" name="backgroundGradient" value="<?= e($page['theme']['backgroundGradient'] ?? '') ?>" placeholder="linear-gradient(135deg, #f5f1eb 0%, #147d69 100%)" maxlength="400"></label>
                <p class="field-help">Use a sintaxe CSS padrão de gradientes. As cores escolhidas acima ainda valem para os cards.</p>
                <div class="gradient-presets"><?php foreach ([
                    'Floresta' => 'linear-gradient(160deg,#153d33 0%,#285044 100%)',
                    'Aurora'   => 'linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%)',
                    'Pôr do sol' => 'linear-gradient(135deg,#f6d365 0%,#fda085 100%)',
                    'Oceano'   => 'linear-gradient(135deg,#0f2027 0%,#203a43 50%,#2c5364 100%)',
                    'Rosa'     => 'linear-gradient(135deg,#f6e7e3 0%,#a5544b 100%)',
                    'Mint'     => 'linear-gradient(135deg,#e0f7fa 0%,#00897b 100%)',
                    'Lilás'    => 'linear-gradient(135deg,#f3e7ff 0%,#7c3aed 100%)',
                    'Carvão'   => 'linear-gradient(135deg,#1a1a1a 0%,#3d3d3d 100%)',
                ] as $name => $val): ?>
                <button type="button" class="gradient-chip" data-gradient="<?= e($val) ?>" title="<?= e($name) ?>" style="background:<?= e($val) ?>"><span><?= e($name) ?></span></button>
                <?php endforeach; ?></div>
            </div>
            <div class="bg-extra" id="bg-image-field" style="<?= ($page['theme']['backgroundType'] ?? 'solid') !== 'image' ? 'display:none' : '' ?>">
                <label class="field"><span>Enviar imagem de fundo <small>(PNG, JPG ou WebP — recomendado 1920×1080 px)</small></span><input type="file" name="backgroundImage_file" accept="image/png,image/jpeg,image/webp"></label>
                <p class="field-help">Ou cole uma URL abaixo. O upload substitui a URL automaticamente.</p>
                <label class="field"><span>URL da imagem de fundo</span><input type="text" name="backgroundImage" value="<?= e($page['theme']['backgroundImage'] ?? '') ?>" placeholder="https://exemplo.com/fundo.jpg" maxlength="2048"></label>
                <p class="field-help">Imagem horizontal de alta resolução. Uma sobreposição suave garante a legibilidade dos textos.</p>
                <?php if (!empty($page['theme']['backgroundImage'])): ?>
                <img class="bg-image-preview" src="<?= e($page['theme']['backgroundImage']) ?>" alt="Prévia da imagem de fundo">
                <label class="check-field"><input type="checkbox" name="remove_backgroundImage"> Remover imagem de fundo atual</label>
                <?php endif; ?>
            </div>

            <h3 class="subsection-title" style="margin-top:10px">Tipografia e layout</h3>
            <div class="field-grid">
                <?php $this->selectField('Tipografia', 'font', $page['theme']['font'], ['sans' => 'Moderna · sem serifa', 'serif' => 'Editorial · com serifa', 'mono' => 'Técnica · monoespaçada']); ?>
                <?php $this->selectField('Tamanho do título', 'titleSize', (string) ($page['theme']['titleSize'] ?? 'xl'), ['sm' => 'Pequeno', 'md' => 'Médio', 'lg' => 'Grande', 'xl' => 'Extra grande (padrão)', '2xl' => 'Enorme']); ?>
                <?php $this->selectField('Formato dos cards', 'radius', $page['theme']['radius'], ['rounded' => 'Bem arredondados', 'soft' => 'Cantos suaves', 'square' => 'Cantos retos']); ?>
                <?php $this->selectField('Estilo do fundo (textura)', 'style', $page['theme']['style'], ['solid' => 'Sem textura', 'gradient' => 'Gradiente suave de acento', 'dotted' => 'Textura pontilhada']); ?>
                <?php $this->selectField('Estilo dos cards', 'buttonStyle', $page['theme']['buttonStyle'], ['filled' => 'Preenchido', 'outline' => 'Somente contorno']); ?>
                <?php $this->selectField('Tema de cores', 'colorScheme', (string) ($page['theme']['colorScheme'] ?? 'light'), ['light' => 'Claro (Light)', 'dark' => 'Escuro (Dark)']); ?>
            </div>
            <div class="form-actions"><button class="button button-primary"><?= icon('check', 18) ?> Salvar aparência</button></div></form>
            <div class="callout"><?= icon('eye', 18) ?><p>As cores individuais dos cards são editadas na aba <strong>Links e cards</strong>. O tema define o estilo geral e dos novos cards.</p></div>
            <script>
            (function(){
                var sel = document.querySelector('[name="backgroundType"]');
                var gf = document.getElementById('bg-gradient-field');
                var imgf = document.getElementById('bg-image-field');
                function upd(){ if(!sel||!gf||!imgf) return; gf.style.display=sel.value==='gradient'?'':'none'; imgf.style.display=sel.value==='image'?'':'none'; }
                if(sel){ sel.addEventListener('change', upd); upd(); }
                document.querySelectorAll('.gradient-chip').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        var inp = document.querySelector('[name="backgroundGradient"]');
                        if(inp){ inp.value = btn.dataset.gradient; inp.dispatchEvent(new Event('input',{bubbles:true})); }
                    });
                });
            })();
            </script>
            <?php elseif ($tab === 'socials'): $socials = array_column($page['socials'], 'url', 'platform'); ?>
            <form class="panel" method="post" action="<?= e($action) ?>" data-live-preview-form data-live-preview-endpoint="<?= e($previewEndpoint) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="socials"><div class="panel-title"><?= icon('globe', 20) ?><div><h2>Encontre sua comunidade</h2><p>Os ícones aparecem abaixo do perfil na página pública.</p></div></div><?php foreach (['instagram' => ['Instagram', 'https://instagram.com/seu-perfil'], 'youtube' => ['YouTube', 'https://youtube.com/@seu-canal'], 'whatsapp' => ['WhatsApp', 'https://wa.me/55DDDNUMERO'], 'facebook' => ['Facebook', 'https://facebook.com/sua-pagina'], 'website' => ['Site', 'https://seu-site.com.br'], 'email' => ['Localização', 'https://maps.google.com/?q=Seu+Endereço']] as $platform => [$label, $placeholder]): ?><div class="social-field"><span><?= icon($platform === 'website' ? 'globe' : ($platform === 'email' ? 'map' : $platform), 22) ?></span><?php $this->field($label, 'social_' . $platform, $socials[$platform] ?? '', 'text', $placeholder); ?></div><?php endforeach; ?><p class="field-help">Deixe o campo em branco para ocultar a rede. Informe a URL completa ou mailto: para e-mail.</p><div class="form-actions"><button class="button button-primary"><?= icon('check', 18) ?> Salvar redes sociais</button></div></form>
            <?php endif; ?>
        </div><aside class="preview-panel"><div class="preview-title"><span><?= icon('eye', 18) ?> Sua página, ao vivo</span><a href="<?= e($action) ?>/preview" target="_blank" rel="noopener" aria-label="Abrir prévia em nova aba"><?= icon('external', 17) ?></a></div><div class="phone-frame"><div class="phone-notch"></div><iframe src="<?= e($action) ?>/preview" data-live-preview-iframe sandbox="allow-same-origin" title="Prévia de <?= e($page['name']) ?>" loading="eager"></iframe></div><p class="preview-caption"><span data-live-preview-status role="status" aria-live="polite">Prévia atualizada</span><br>Alterações são aplicadas ao site público só ao salvar.</p><div class="preview-metrics"><span><?= icon('eye', 15) ?> <?= (int) $stats['visits'] ?> visualizações</span><span><?= icon('arrow', 15) ?> <?= (int) $stats['clicks'] ?> cliques</span></div></aside></div>
        <?php $this->end();
    }

    private function cardEditor(string $action, array $card): void
    {
        $isMaps   = $card['type'] === 'maps';
        $isPhoto  = $card['type'] === 'photo';
        $isBanner = in_array($card['type'], ['link', 'image'], true)
                    && ($card['layout'] ?? '') === 'banner';
        ?><form class="panel card-editor" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-live-preview-form data-live-preview-endpoint="<?= e($action) ?>/live-preview"><?php $this->csrf(); ?><input type="hidden" name="action" value="card-save"><input type="hidden" name="card_id" value="<?= e($card['id']) ?>"><div class="panel-title"><?= icon('edit', 20) ?><div><h2>Personalizar card</h2><p>Conteúdo e aparência em um só lugar.</p></div><a class="icon-button close-editor" href="<?= e($action) ?>" aria-label="Fechar editor">×</a></div>
        <?php if ($isPhoto): ?>
            <input type="hidden" name="title" value="">
            <input type="hidden" name="description" value="">
            <input type="hidden" name="layout" value="mini">
            <input type="hidden" name="cta" value="">
            <input type="hidden" name="icon" value="link">
            <input type="hidden" name="badge" value="">
            <?php $this->field('Link de destino', 'url', $card['url'], 'text', 'https://...'); ?>
            <label class="field"><span>Imagem</span><input type="file" name="image_file" accept="image/png,image/jpeg,image/webp"></label>
            <p class="field-help">PNG, JPG ou WebP, até 10 MB.</p>
            <?php $this->field('Ou imagem por URL', 'image', $card['image'], 'text', 'https://...'); ?>
            <?php if ($card['image']): ?><img class="card-image-preview" src="<?= e($card['image']) ?>" alt="Imagem atual"><label class="check-field"><input type="checkbox" name="remove_image"> Remover imagem</label><?php endif; ?>
            <?php $this->selectField('Tamanho', 'photoSize', (string) ($card['photoSize'] ?? 'md'), ['xs' => 'Muito pequeno (60px)', 'sm' => 'Pequeno (80px)', 'md' => 'Médio (120px)', 'lg' => 'Grande (180px)', 'xl' => 'Extra grande (240px)', 'full' => 'Full: largura total, proporção original']); ?>
            <p class="field-help">A imagem é exibida pura, sem card, sem borda, sem background.</p>
            <div class="checkbox-stack"><label class="check-field"><input type="checkbox" name="enabled"<?= checked($card['enabled']) ?>> Exibir na página</label><label class="check-field"><input type="checkbox" name="newTab"<?= checked($card['newTab'] ?? true) ?>> Abrir link em nova aba</label></div>
            <input type="hidden" name="highlight" value="0">
            <input type="hidden" name="showArrow" value="0">
            <input type="hidden" name="color" value="<?= e($card['color'] ?? '#ffffff') ?>">
            <input type="hidden" name="textColor" value="<?= e($card['textColor'] ?? '#000000') ?>">
            <div class="form-actions"><a class="button button-light" href="<?= e($action) ?>">Fechar</a><button class="button button-primary"><?= icon('check', 17) ?> Salvar</button></div>
        </form><?php return; endif; ?>
        <?php $this->field('Título', 'title', $card['title'], 'text', 'Opcional', false, 160); ?>
        <label class="field"><span>Descrição ou mensagem</span><textarea name="description" rows="3" maxlength="1000"><?= e($card['description']) ?></textarea></label>
        <?php if ($isMaps): ?>
            <label class="field">
                <span>Link ou endereço do local</span>
                <textarea name="embedSrc" rows="3" placeholder="Cole aqui: link do Google Maps, código &lt;iframe&gt;, endereço ou coordenadas"><?= e($card['embedSrc'] ?? '') ?></textarea>
            </label>
            <p class="field-help">Aceita qualquer formato: link curto (<strong>share.google/...</strong>), link completo (<strong>google.com/maps/...</strong>), código <strong>&lt;iframe&gt;</strong> de incorporação, endereço (<strong>Rua X, 123, São Paulo</strong>) ou coordenadas (<strong>-23.5505, -46.6333</strong>).</p>
            <?php $this->field('Texto do botão primário', 'cta', (string) ($card['cta'] ?? 'Abrir no Maps'), 'text', 'Abrir no Maps', false, 80); ?>
            <p class="field-help"><?= icon('map', 12) ?> O campo <strong>Etiqueta</strong> abaixo vira um segundo botão — ex: "Como chegar".</p>
            <input type="hidden" name="url" value="<?= e($card['url'] ?? '') ?>">
            <input type="hidden" name="image" value="">
            <input type="hidden" name="layout" value="compact">
        <?php elseif (in_array($card['type'], ['link', 'image'], true)): ?>
            <?php $this->field('Link de destino', 'url', $card['url'], 'text', 'https://..., mailto:... ou tel:...'); ?>
            <p class="field-help">Um card ativo precisa de um destino válido. Desative o card enquanto o prepara.</p>
        <?php else: ?>
            <input type="hidden" name="url" value="">
        <?php endif; ?>
        <?php if (in_array($card['type'], ['link', 'image'], true)): ?><label class="field"><span>Imagem do card</span><input type="file" name="image_file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">PNG, JPG ou WebP, até 2 MB. Prefira imagens horizontais.</p><?php $this->field('Ou imagem por URL', 'image', $card['image'], 'text', 'https://...'); ?><?php if ($card['image']): ?><img class="card-image-preview" src="<?= e($card['image']) ?>" alt="Imagem atual do card"><label class="check-field"><input type="checkbox" name="remove_image"> Remover imagem</label><?php endif; ?><?php else: ?><?php if (!$isMaps): ?><input type="hidden" name="image" value="<?= e($card['image']) ?>"><?php endif; ?><?php endif; ?>
        <?php if (in_array($card['type'], ['link', 'image'], true)): ?><?php $this->selectField('Layout do card', 'layout', (string) ($card['layout'] ?? 'mini'), ['mini' => 'Mini: ícone pequeno + texto (padrão)', 'compact' => 'Compacto: miniatura + texto + seta', 'cover' => 'Imagem em destaque: imagem + texto sobreposto', 'button' => 'Imagem + botão Acessar', 'banner' => 'Banner: só imagem, full width']); ?><?php $this->field('Texto do botão', 'cta', (string) ($card['cta'] ?? 'Acessar'), 'text', 'Acessar', false, 80); ?><?php endif; ?>
        <div class="field-grid"><?php $this->selectField('Ícone', 'icon', $card['icon'], ['link' => 'Link', 'globe' => 'Site', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'mail' => 'E-mail', 'calendar' => 'Calendário', 'map' => 'Localização', 'heart' => 'Coração', 'book' => 'Livro', 'phone' => 'Telefone', 'arrow' => 'Seta']); ?><?php $this->field('Etiqueta de destaque', 'badge', $card['badge'], 'text', 'Ex.: Novidade', false, 30); ?></div>
        <div class="color-fields card-colors"><?php foreach (['color' => 'Cor do card', 'textColor' => 'Cor do texto'] as $key => $label): ?><label><input type="color" name="<?= e($key) ?>" value="<?= e($card[$key]) ?>"><span><?= e($label) ?></span></label><?php endforeach; ?></div>
        <h4 class="card-typo-title">Tamanho e alinhamento</h4>
        <div class="field-grid">
            <?php $this->selectField('Tamanho do card', 'cardItemSize', (string) ($card['cardItemSize'] ?? ''), ['' => 'Padrão da página', 'xs' => 'Muito pequeno', 'sm' => 'Pequeno', 'md' => 'Médio', 'lg' => 'Grande', 'xl' => 'Extra grande']); ?>
            <?php $this->selectField('Alinhamento', 'cardItemAlign', (string) ($card['cardItemAlign'] ?? ''), ['' => 'Padrão da página', 'left' => 'Esquerda', 'center' => 'Centro', 'right' => 'Direita']); ?>
        </div>
        <label class="field">
            <span>Opacidade do fundo do card <small><?= (int) ($card['cardOpacity'] ?? 100) ?>%</small></span>
            <input type="range" name="cardOpacity" min="0" max="100" step="5"
                   value="<?= (int) ($card['cardOpacity'] ?? 100) ?>"
                   oninput="this.previousElementSibling.querySelector('small').textContent = this.value + '%'">
            <span class="field-help" style="margin:4px 0 0">0% = totalmente transparente (glass) · 100% = cor sólida</span>
        </label>
        <h4 class="card-typo-title">Tipografia do título</h4>
        <div class="field-grid">
            <?php $this->selectField('Peso da fonte', 'titleWeight', (string) ($card['titleWeight'] ?? ''), ['' => 'Padrão do tema', '300' => 'Leve (300)', '400' => 'Regular (400)', '500' => 'Médio (500)', '600' => 'Semi-negrito (600)', '700' => 'Negrito (700)', '800' => 'Extra-negrito (800)', '900' => 'Preto (900)']); ?>
            <?php $this->selectField('Tamanho da fonte', 'titleFontSize', (string) ($card['titleFontSize'] ?? ''), ['' => 'Padrão do tema', 'xs' => 'Extra pequeno', 'sm' => 'Pequeno', 'md' => 'Médio', 'lg' => 'Grande', 'xl' => 'Extra grande']); ?>
        </div>
        <div class="color-fields card-colors">
            <label><input type="color" name="titleColor" value="<?= e($card['titleColor'] ?? '#000000') ?>"><span>Cor do título <small>(substitui a cor do texto)</small></span></label>
            <label><input type="color" name="descColor" value="<?= e($card['descColor'] ?? '#000000') ?>"><span>Cor da descrição</span></label>
        </div>
        <p class="field-help">Deixe no padrão para herdar as cores e pesos do tema global.</p>
        <div class="checkbox-stack"><label class="check-field"><input type="checkbox" name="enabled"<?= checked($card['enabled']) ?>> Exibir este card na página</label><label class="check-field"><input type="checkbox" name="showArrow"<?= checked($card['showArrow'] ?? true) ?>> Mostrar botão de seta no card</label><label class="check-field"><input type="checkbox" name="highlight"<?= checked($card['highlight']) ?>> Dar destaque visual ao card</label><label class="check-field"><input type="checkbox" name="newTab"<?= checked($card['newTab']) ?>> Abrir o link em uma nova aba</label></div><div class="form-actions"><a class="button button-light" href="<?= e($action) ?>">Fechar</a><button class="button button-primary"><?= icon('check', 17) ?> Salvar card</button></div></form><?php
    }

    public function templates(array $user): void
    {
        $this->start($user, 'templates', 'Modelos');
        ?><div class="page-heading"><div><div class="eyebrow">UM PONTO DE PARTIDA. TODO SEU.</div><h1>O jeito da sua página<span class="heading-dot">.</span></h1><p>Seis identidades visuais para começar. Personalize tudo depois.</p></div></div><section class="templates-grid"><?php foreach (self::templateThemes() as $key => $theme): ?><article class="template-card"><div class="template-preview" style="background:<?= e($theme['background']) ?>;color:<?= e($theme['text']) ?>;--template-accent:<?= e($theme['accent']) ?>;--template-card:<?= e($theme['cardBackground']) ?>"><span class="template-avatar"><?= icon('link', 26) ?></span><strong>Sua comunidade</strong><span class="template-small">Perto de quem importa.</span><div class="template-demo-link">Conheça nosso projeto <?= icon('arrow', 13) ?></div><div class="template-demo-link">Fale com a gente <?= icon('arrow', 13) ?></div><div class="template-demo-link">Nossos encontros <?= icon('arrow', 13) ?></div><span class="template-small">Prévia de estilo</span></div><div class="template-info"><div><h2><?= e(self::templateNames()[$key]) ?></h2><p><?= e(['natural' => 'Leve, próximo e acolhedor.', 'forest' => 'Profundo, orgânico e marcante.', 'rose' => 'Delicado com um toque editorial.', 'night' => 'Contraste e personalidade.', 'minimal' => 'Só o essencial. Sem distrações.', 'sunny' => 'Luz e energia em cada conexão.'][$key]) ?></p></div><a href="/admin/new?template=<?= e($key) ?>" class="button button-light">Usar modelo <?= icon('arrow', 16) ?></a></div></article><?php endforeach; ?></section><?php $this->end();
    }

    public function analytics(array $user, array $stats, array $rows): void
    {
        $this->start($user, 'analytics', 'Estatísticas');
        ?><div class="page-heading"><div><div class="eyebrow">CADA CLIQUE É UMA CONEXÃO</div><h1>Conheça seu alcance<span class="heading-dot">.</span></h1><p>Visualizações e cliques reais, contados desde o início.</p></div></div><section class="stats-row analytics-stats"><?php foreach (['visits' => 'Visualizações de páginas', 'clicks' => 'Cliques nos cards', 'published' => 'Páginas publicadas'] as $key => $label): ?><div class="stat-box"><span><?= e($label) ?></span><strong><?= number_format((int) $stats[$key], 0, ',', '.') ?></strong><span class="stat-hint">Total acumulado</span></div><?php endforeach; ?></section><section class="panel"><div class="panel-title"><?= icon('chart', 21) ?><div><h2>Conexões por página</h2><p>As prévias do editor não entram na contagem.</p></div></div><div class="analytics-table" role="table" aria-label="Estatísticas das páginas"><div class="analytics-row analytics-table-head" role="row"><span role="columnheader">Página</span><span role="columnheader">Estado</span><span role="columnheader">Visualizações</span><span role="columnheader">Cliques</span></div><?php foreach ($rows as $row): $page = $row['page']; ?><div class="analytics-row" role="row"><a role="cell" href="/admin/pages/<?= e($page['id']) ?>"><strong><?= e($page['name']) ?></strong><small>/<?= e($page['slug']) ?></small></a><span role="cell" class="status-badge <?= e($page['status']) ?>"><?= e(self::STATUS[$page['status']]) ?></span><span role="cell"><?= (int) $row['stats']['visits'] ?></span><span role="cell"><?= (int) $row['stats']['clicks'] ?></span></div><?php endforeach; ?></div></section><p class="field-help">Contagem de acessos, não de pessoas únicas. Visitas repetidas podem ser contabilizadas; cliques em ícones sociais não entram nos cliques dos cards.</p><?php $this->end();
    }

    public function settings(array $user): void
    {
        $this->start($user, 'settings', 'Configurações');
        ?><div class="page-heading"><div><div class="eyebrow">SEU ESPAÇO, SOB SEU CONTROLE</div><h1>Configurações<span class="heading-dot">.</span></h1><p>Informações da conta, segurança e uma cópia dos seus conteúdos.</p></div></div><div class="settings-layout"><section class="panel"><div class="panel-title"><?= icon('shield', 21) ?><div><h2>Acesso administrativo</h2><p>Somente a conta autorizada pode editar as páginas.</p></div></div><div class="account-info"><span class="account-avatar large"><?= e(mb_strtoupper(mb_substr($user['name'] ?: 'CO', 0, 2))) ?></span><div><strong><?= e($user['name']) ?></strong><span><?= e($user['email']) ?></span><span class="verified-label"><?= icon('check', 14) ?> Administrador autorizado</span></div></div><div class="callout"><?= icon('shield', 18) ?><p>Autenticação por senha, sessão segura com cookie HttpOnly e formulários protegidos por CSRF. Suas páginas públicas não exigem login.</p></div><form method="post" action="/auth/logout" class="form-actions"><?php $this->csrf(); ?><button class="button button-light"><?= icon('logout', 17) ?> Sair da conta</button></form></section><section class="panel"><div class="panel-title"><?= icon('download', 21) ?><div><h2>Seu conteúdo com você</h2><p>Exporte uma cópia das páginas e configurações.</p></div></div><p class="panel-copy">O arquivo JSON contém nomes, endereços, cards, ordem, temas e estados das páginas. Imagens enviadas são referenciadas por endereço; seus arquivos binários não fazem parte desta exportação.</p><a href="/admin/export" class="button button-primary"><?= icon('download', 17) ?> Exportar páginas</a><div class="technical-note"><strong>Construído em PHP POO</strong><p>Sem frameworks. PHP valida e persiste; JavaScript nativo só transmite as mudanças não salvas para a prévia, sem gravá-las.</p></div></section></div><?php $this->end();
    }

    public function confirm(array $user, array $page, ?array $card = null): void
    {
        $this->start($user, 'pages', $card ? 'Excluir card' : 'Excluir página');
        $action = '/admin/pages/' . $page['id'];
        ?><a class="back-link" href="<?= e($action) ?>">← Voltar para a página</a><section class="panel confirm-panel"><span class="confirm-symbol"><?= icon('trash', 32) ?></span><h1><?= $card ? 'Excluir este card?' : 'Excluir esta página?' ?></h1><p><?= $card ? 'O card “' . e($card['title']) . '” será removido da página.' : 'A página “' . e($page['name']) . '” e todos os seus cards serão removidos. O link público deixará de funcionar.' ?></p><p class="danger-text">Essa ação não pode ser desfeita.</p><form method="post" action="<?= e($action) ?>"><?php $this->csrf(); ?><input type="hidden" name="action" value="<?= $card ? 'card-delete' : 'delete' ?>"><?php if ($card): ?><input type="hidden" name="card_id" value="<?= e($card['id']) ?>"><?php else: ?><?php $this->field('Digite o nome da página para confirmar', 'confirmation', '', 'text', $page['name'], true); ?><?php endif; ?><div class="form-actions"><a class="button button-light" href="<?= e($action) ?>">Cancelar</a><button class="button button-danger-solid"><?= icon('trash', 17) ?> Confirmar exclusão</button></div></form></section><?php $this->end();
    }

    public function error(string $title, string $message, string $back): void
    {
        $this->head($title, 'error-body');
        ?><main id="main" class="error-page"><a href="/" class="brand" aria-label="CopLinks, início"><?php $this->wordmark(); ?></a><div class="error-symbol"><?= icon('link', 37) ?></div><h1><?= e($title) ?></h1><p><?= e($message) ?></p><div class="error-actions"><a class="button button-primary" href="<?= e($back) ?>">Voltar <?= icon('arrow', 17) ?></a><span>Se foi um erro de formulário, use o botão Voltar do navegador para manter os dados preenchidos.</span></div></main></body></html><?php
    }

    private function field(string $label, string $name, string $value, string $type = 'text', string $placeholder = '', bool $required = false, int $max = 2048): void
    {
        ?><label class="field"><span><?= e($label) ?><?= $required ? '<span class="required-mark">*</span>' : '' ?></span><input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>" maxlength="<?= $max ?>"<?= $required ? ' required' : '' ?>></label><?php
    }

    private function selectField(string $label, string $name, string $value, array $options): void
    {
        ?><label class="field"><span><?= e($label) ?></span><select name="<?= e($name) ?>"><?php foreach ($options as $key => $text): ?><option value="<?= e($key) ?>"<?= selected($value, $key) ?>><?= e($text) ?></option><?php endforeach; ?></select></label><?php
    }
}
