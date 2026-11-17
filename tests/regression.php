<?php

declare(strict_types=1);

// Todos os dados e identidades abaixo são sintéticos e ficam apenas neste
// processo de testes e em um SQLite temporário. Não alteram o Preview ou MySQL.
require dirname(__DIR__) . '/bootstrap.php';
ob_start();

use Bio\AdminView;
use Bio\App;
use Bio\Auth;
use Bio\Database;
use Bio\MediaRepository;
use Bio\PageRepository;
use Bio\PageValidator;
use Bio\PublicView;

$path = tempnam(sys_get_temp_dir(), 'bio-test-');
if ($path === false) {
    throw new RuntimeException('Não foi possível criar banco de teste.');
}
putenv('DATABASE_URL=sqlite://local' . $path);
putenv('PUBLIC_ORIGIN=http://localhost:3000');
putenv('ADMIN_PASSWORD=test-password-only');
putenv('ADMIN_EMAILS=admin@example.test');
$tests = 0;
function check(bool $condition, string $description): void
{
    global $tests;
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $description);
    }
    $tests++;
    echo 'OK ' . $description . PHP_EOL;
}
try {
    Database::migrate();
    $repo = new PageRepository();
    check($repo->stats()['total'] === 36, '36 páginas iniciais');
    Database::migrate();
    check($repo->stats()['total'] === 36, 'migração e seed idempotentes');
    check($repo->stats()['published'] === 0 && $repo->stats()['draft'] === 36, 'rascunhos não publicados');
    $page = $repo->create(['name' => 'Teste <script>alert(1)</script>', 'title' => 'Página de teste', 'slug' => 'teste-isolado', 'theme' => AdminView::templateThemes()['forest']]);
    check($repo->find($page['id'])['name'] === $page['name'], 'criação e leitura');
    $card = ['id' => bin2hex(random_bytes(8)), 'type' => 'link', 'title' => 'Link válido', 'url' => 'https://example.com/', 'enabled' => true, 'color' => '#285044', 'textColor' => '#f1f5e9', 'newTab' => true, 'highlight' => true];
    $page = $repo->update($page['id'], ['cards' => [$card], 'status' => 'published']);
    check($page['name'] !== '' && $page['theme']['background'] === '#153d33', 'update parcial preserva perfil e tema');
    check($page['cards'][0]['layout'] === 'compact' && $page['cards'][0]['cta'] === 'Acessar', 'valores padrão do layout e botão');
    $longDescription = str_repeat('d', 900);
    $longDescriptionPage = $page;
    $longDescriptionPage['cards'][0]['description'] = $longDescription;
    check(PageValidator::validate($longDescriptionPage)['cards'][0]['description'] === $longDescription, 'descrição de card aceita o limite publicado de 1.000 caracteres');
    $copy = $repo->duplicate($page['id']);
    check($copy['status'] === 'draft' && $copy['slug'] !== $page['slug'] && $copy['cards'][0]['id'] !== $card['id'], 'duplicação com URL e ids independentes');
    check(count($repo->all('teste-isolado')) === 2, 'busca por URL');
    $imagePath = tempnam(sys_get_temp_dir(), 'bio-image-test-');
    file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZ1kAAAAASUVORK5CYII='));
    $media = new MediaRepository();
    try {
        $mediaUrl = $media->store(['tmp_name' => $imagePath, 'error' => UPLOAD_ERR_OK]);
    } finally {
        unlink($imagePath);
    }
    $page = $repo->update($page['id'], ['avatar' => $mediaUrl]);
    $copy = $repo->update($copy['id'], ['avatar' => $mediaUrl]);
    check($media->find(substr($mediaUrl, 7)) !== null, 'upload real validado e armazenado em BLOB');
    foreach (['javascript:alert(1)', 'https://user:password@example.com/', "https://example.com/\r\nX-Test:invalid"] as $bad) {
        try {
            $payload = $page;
            $payload['cards'][0]['url'] = $bad;
            PageValidator::validate($payload);
            check(false, 'rejeitar URL insegura');
        } catch (InvalidArgumentException) {
            check(true, 'URL insegura rejeitada');
        }
    }
    try {
        $repo->create(['name' => 'Teste', 'title' => 'Teste', 'slug' => 'admin']);
        check(false, 'slug reservado');
    } catch (InvalidArgumentException) {
        check(true, 'slug reservado rejeitado');
    }
    try {
        $repo->create(['name' => 'Teste', 'title' => 'Teste', 'slug' => 'teste-isolado']);
        check(false, 'slug duplicado');
    } catch (InvalidArgumentException) {
        check(true, 'slug duplicado rejeitado');
    }
    $repo->track($page['id']);
    $repo->track($page['id'], $card['id']);
    check($repo->pageStats($page['id'])['visits'] === 1 && $repo->pageStats($page['id'])['clicks'] === 1, 'visitas e cliques distintos');
    ob_start();
    PublicView::render($page);
    $html = ob_get_clean();
    check(str_contains($html, '/go/' . $page['id'] . '/' . $card['id']), 'cards públicos usam redirecionamento rastreado');
    check(!str_contains($html, '<script>'), 'renderização sem script e sem XSS');
    check(str_contains($html, '--bio-card-background:#285044'), 'cor individual do fundo do card aplicada');
    check(str_contains($html, 'content="http://localhost:3000' . $mediaUrl . '"') && str_contains($html, 'rel="canonical"') && str_contains($html, 'name="twitter:card"'), 'metadados e imagem absoluta na origem configurada');
    $page = $repo->update($page['id'], ['avatar' => '']);
    check($media->find(substr($mediaUrl, 7)) !== null, 'imagem compartilhada preservada para outra página');
    $copy = $repo->update($copy['id'], ['avatar' => '']);
    check($media->find(substr($mediaUrl, 7)) === null, 'mídia removida após perder a última referência');
    ob_start();
    PublicView::render($copy, true);
    $preview = ob_get_clean();
    check(str_contains($preview, 'Prévia · não publicada') && str_contains($preview, 'href="https://example.com/"'), 'prévia draft e destino direto');

    // Autenticação via sessão PHP nativa (simulada diretamente para os testes)
    // Já que session_start() não funciona em CLI, injetamos a sessão diretamente.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['bio_admin_user'] = ['openId' => 'local-admin', 'name' => 'Administrador', 'email' => 'admin@example.test'];
    $_SESSION['bio_csrf_token'] = bin2hex(random_bytes(32));
    $user = Auth::user();
    check($user !== null && $user['email'] === 'admin@example.test', 'sessão válida autorizada');
    $csrf = Auth::csrf();
    check(Auth::verifyCsrf($csrf) && !Auth::verifyCsrf('invalid'), 'proteção CSRF');

    // Sessão inválida/ausente
    $_SESSION['bio_admin_user'] = null;
    check(Auth::user() === null, 'sessão vazia bloqueada');
    $_SESSION['bio_admin_user'] = ['openId' => 'local-admin', 'name' => 'Administrador', 'email' => 'admin@example.test'];

    $user = Auth::requireUser();
    $view = new AdminView();
    foreach (['dashboard', 'editor', 'newPage', 'templates', 'analytics', 'settings', 'confirm'] as $screen) {
        ob_start();
        match ($screen) {
            'dashboard' => $view->dashboard($user, $repo->all(), $repo->stats(), '', 'all', 'grid'),
            'editor' => $view->editor($user, $page, 'links', $page['cards'][0], $repo->pageStats($page['id'])),
            'newPage' => $view->newPage($user, 'natural'),
            'templates' => $view->templates($user),
            'analytics' => $view->analytics($user, $repo->stats(), [['page' => $page, 'stats' => $repo->pageStats($page['id'])]]),
            'settings' => $view->settings($user),
            'confirm' => $view->confirm($user, $page),
        };
        $screenHtml = ob_get_clean();
        check(str_contains($screenHtml, '</html>') && !str_contains($screenHtml, '<script>alert(1)</script>'), 'tela ' . $screen . ' completa e escapada');
        $dom = new DOMDocument();
        @$dom->loadHTML($screenHtml);
        $xpath = new DOMXPath($dom);
        check($xpath->query('//form[@method="post"]')->length === $xpath->query('//form[@method="post"]/input[@name="_csrf"]')->length, 'CSRF em formulários ' . $screen);
    }
    foreach (['details', 'appearance', 'socials'] as $tab) {
        ob_start();
        $view->editor($user, $page, $tab, null, $repo->pageStats($page['id']));
        $screenHtml = ob_get_clean();
        check(str_contains($screenHtml, 'value="' . $tab . '"') && str_contains($screenHtml, '<iframe'), 'aba ' . $tab . ' conectada ao salvamento e prévia');
    }
    $headingCard = $page['cards'][0];
    $headingCard['type'] = 'heading';
    ob_start();
    $view->editor($user, $page, 'links', $headingCard, $repo->pageStats($page['id']));
    $headingEditorHtml = ob_get_clean();
    check(!str_contains($headingEditorHtml, '<select name="layout"') && str_contains($headingEditorHtml, 'type="hidden" name="layout" value="compact"'), 'card heading não anuncia layout/CTA sem suporte');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/pages/' . $page['id'] . '?card=' . $card['id'];
    $_GET['card'] = $card['id'];
    ob_start();
    (new App())->run();
    $appHtml = ob_get_clean();
    check(str_contains($appHtml, 'Links que conectam'), 'rota PHP resolve editor com página do banco isolado');
    check(str_contains($appHtml, 'name="layout"') && str_contains($appHtml, 'name="cta"') && str_contains($appHtml, 'data-live-preview-endpoint'), 'editor oferece layouts, botão e sincronização ao vivo');
    $_POST = [
        '_csrf' => $csrf,
        'card_id' => $card['id'],
        'title' => 'Título ao vivo <script>alert(2)</script>',
        'description' => 'Atualização instantânea sem salvar',
        'url' => 'https://example.com/ao-vivo',
        'image' => '',
        'image_preview_data' => 'data:image/png;base64,' . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZ1kAAAAASUVORK5CYII=',
        'layout' => 'cover',
        'cta' => 'Abrir agora',
        'color' => '#2563eb',
        'textColor' => '#ffffff',
        'enabled' => 'on',
        'newTab' => 'on',
        'highlight' => 'on',
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/pages/' . $page['id'] . '/live-preview';
    ob_start();
    (new App())->run();
    $livePreviewHtml = ob_get_clean();
    check(str_contains($livePreviewHtml, 'bio-card--layout-cover') && str_contains($livePreviewHtml, 'Atualização instantânea sem salvar'), 'rota live-preview renderiza as alterações de layout e texto');
    check(str_contains($livePreviewHtml, 'data:image/png;base64,') && str_contains($livePreviewHtml, 'Prévia · alterações ainda não salvas'), 'imagem temporária e estado não salvo chegam ao iframe');
    check(!str_contains($livePreviewHtml, '<script>alert(2)</script>'), 'HTML do preview continua escapado');
    $storedAfterPreview = $repo->find($page['id']);
    check($storedAfterPreview['cards'][0]['title'] === 'Link válido' && $storedAfterPreview['cards'][0]['layout'] === 'compact', 'preview em tempo real não persiste mudanças ao banco');
    $repo->delete($copy['id']);
    $repo->delete($page['id']);
    check($repo->stats()['total'] === 36 && $repo->stats()['visits'] === 0 && $repo->stats()['clicks'] === 0, 'exclusão limpa somente dados de teste');
    echo PHP_EOL . $tests . ' verificações concluídas em banco temporário.' . PHP_EOL;
} finally {
    @unlink($path);
    ob_end_flush();
}