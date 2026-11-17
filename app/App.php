<?php

declare(strict_types=1);

namespace Bio;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class App
{
    private PageRepository $pages;
    private AdminView $view;

    public function __construct()
    {
        $this->pages = new PageRepository();
        $this->view = new AdminView();
    }

    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            if ($path === '/health') {
                Database::connection()->query('SELECT 1');
                header('Content-Type: application/json');
                echo '{"ok":true}';
                return;
            }
            if ($path === '/auth/login' && $method === 'GET') {
                Auth::beginLogin();
            }
            if ($path === '/auth/login' && $method === 'POST') {
                Auth::callback();
            }
            if ($path === '/auth/logout' && $method === 'POST') {
                $this->protect();
                Auth::logout();
            }
            if (preg_match('~^/media/([a-f0-9]{16})$~', $path, $match) && $method === 'GET') {
                $media = (new MediaRepository())->find($match[1]);
                if (!$media) {
                    throw new RuntimeException('Imagem não encontrada.', 404);
                }
                header('Content-Type: ' . $media['mime']);
                header('Cache-Control: no-cache');
                echo is_resource($media['content']) ? stream_get_contents($media['content']) : $media['content'];
                return;
            }
            if (preg_match('~^/go/([a-f0-9]{16})/([a-f0-9]{16})$~', $path, $match) && $method === 'GET') {
                $page = $this->page($match[1]);
                if ($page['status'] !== 'published') {
                    throw new RuntimeException('Esta página não está disponível.', 404);
                }
                foreach ($page['cards'] as $card) {
                    if ($card['id'] === $match[2] && $card['enabled'] && in_array($card['type'], ['link', 'image', 'maps'], true)) {
                        $url = $card['url'];
                        if (!preg_match('~^(https?://|mailto:|tel:)~i', $url) || preg_match('/[\r\n]/', $url)) {
                            throw new RuntimeException('Link indisponível.', 404);
                        }
                        $this->pages->track($page['id'], $card['id']);
                        header('Location: ' . $url, true, 302);
                        return;
                    }
                }
                throw new RuntimeException('Link não encontrado.', 404);
            }
            if ($path === '/') {
                if (Auth::user()) {
                    redirect('/admin');
                }
                $this->view->landing();
                return;
            }
            if ($path === '/login') {
                if (Auth::user()) {
                    redirect('/admin');
                }
                $error = (string) ($_GET['error'] ?? '');
                $this->view->loginForm($error);
                return;
            }
            if (str_starts_with($path, '/admin')) {
                $user = Auth::user();
                if (!$user) {
                    http_response_code(401);
                    $this->view->landing('Entre com a conta autorizada para acessar o painel.');
                    return;
                }
                if ($method === 'POST') {
                    $this->protect();
                    if (preg_match('~^/admin/pages/([a-f0-9]{16})/live-preview$~', $path, $previewMatch)) {
                        $this->livePreview($this->page($previewMatch[1]));
                        return;
                    }
                    $this->adminPost($path);
                    return;
                }
                if ($method !== 'GET') {
                    throw new RuntimeException('Método não permitido.', 405);
                }
                $this->adminGet($path, $user);
                return;
            }
            if ($method === 'GET' && preg_match('~^/([a-z0-9][a-z0-9-]{0,63})/?$~', $path, $match)) {
                $page = $this->pages->bySlug($match[1]);
                if (!$page || $page['status'] !== 'published') {
                    throw new RuntimeException('Esta página ainda não está disponível.', 404);
                }
                $this->pages->track($page['id']);
                (new PublicView())->render($page);
                return;
            }
            throw new RuntimeException('Página não encontrada.', 404);
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            $this->view->error('Confira os dados', $error->getMessage(), $this->safeBack($path));
        } catch (RuntimeException $error) {
            $status = in_array($error->getCode(), [400, 401, 403, 404, 405, 422], true) ? $error->getCode() : 500;
            http_response_code($status);
            $this->view->error($status === 404 ? 'Este link não está disponível' : 'Não foi possível continuar', $error->getMessage(), $this->safeBack($path));
        } catch (Throwable $error) {
            error_log('Bio error: ' . get_class($error) . ' / ' . $error->getCode());
            http_response_code(500);
            $this->view->error('Algo não saiu como esperado', 'Tente novamente em instantes. Nenhuma credencial foi exposta.', '/');
        }
    }

    private function adminGet(string $path, array $user): void
    {
        if ($path === '/admin') {
            $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
            $status = in_array($_GET['status'] ?? '', ['draft', 'published', 'inactive'], true) ? $_GET['status'] : 'all';
            $layout = ($_GET['view'] ?? '') === 'list' ? 'list' : 'grid';
            $this->view->dashboard($user, $this->pages->all($q, $status), $this->pages->stats(), $q, $status, $layout);
            return;
        }
        if ($path === '/admin/new') {
            $this->view->newPage($user, (string) ($_GET['template'] ?? 'natural'));
            return;
        }
        if ($path === '/admin/templates') {
            $this->view->templates($user);
            return;
        }
        if ($path === '/admin/analytics') {
            $all = $this->pages->all();
            $rows = [];
            foreach ($all as $page) {
                $rows[] = ['page' => $page, 'stats' => $this->pages->pageStats($page['id'])];
            }
            $this->view->analytics($user, $this->pages->stats(), $rows);
            return;
        }
        if ($path === '/admin/settings') {
            $this->view->settings($user);
            return;
        }
        if ($path === '/admin/export') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="coplinks-paginas-' . date('Y-m-d') . '.json"');
            echo json_encode(['version' => 1, 'exportedAt' => gmdate('c'), 'pages' => $this->pages->export()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return;
        }
        if (preg_match('~^/admin/pages/([a-f0-9]{16})(/preview|/delete|/card-delete)?$~', $path, $matches)) {
            $page = $this->page($matches[1]);
            $suffix = $matches[2] ?? '';
            if ($suffix === '/preview') {
                (new PublicView())->render($page, true);
            } elseif ($suffix === '/delete') {
                $this->view->confirm($user, $page);
            } elseif ($suffix === '/card-delete') {
                $card = $this->card($page, (string) ($_GET['card'] ?? ''));
                $this->view->confirm($user, $page, $card);
            } else {
                $tab = in_array($_GET['tab'] ?? '', ['details', 'appearance', 'socials'], true) ? $_GET['tab'] : 'links';
                $card = isset($_GET['card']) ? $this->card($page, (string) $_GET['card']) : null;
                $this->view->editor($user, $page, $tab, $card, $this->pages->pageStats($page['id']));
            }
            return;
        }
        throw new RuntimeException('Página não encontrada.', 404);
    }

    /** Render the in-memory editor state for the authenticated live-preview iframe. */
    private function livePreview(array $page): void
    {
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $page['_live_preview'] = true;
        $cardId = $this->liveValue('card_id', 32);
        if ($cardId !== '') {
            foreach ($page['cards'] as $index => $card) {
                if (($card['id'] ?? '') !== $cardId) {
                    continue;
                }
                foreach (['title' => 160, 'description' => 1000, 'url' => 2048, 'image' => 2048, 'icon' => 20, 'color' => 7, 'textColor' => 7, 'badge' => 40, 'layout' => 20, 'cta' => 80, 'titleWeight' => 10, 'titleFontSize' => 6, 'titleColor' => 7, 'descColor' => 7, 'cardItemSize' => 4, 'cardItemAlign' => 8, 'embedSrc' => 2048, 'photoSize' => 10] as $key => $limit) {
                    $page['cards'][$index][$key] = $this->liveValue($key, $limit, (string) ($card[$key] ?? ''));
                }
                $page['cards'][$index]['cardOpacity'] = max(0, min(100, (int) ($this->liveValue('cardOpacity', 3, (string) ($card['cardOpacity'] ?? 100)))));
                $previewImage = $this->liveValue('image_preview_data', 2_800_100);
                if ($previewImage !== '') {
                    $page['cards'][$index]['image'] = $previewImage;
                } elseif (isset($_POST['remove_image'])) {
                    $page['cards'][$index]['image'] = '';
                }
                foreach (['enabled', 'newTab', 'highlight'] as $key) {
                    $page['cards'][$index][$key] = isset($_POST[$key]);
                }
                $page['cards'][$index]['showArrow'] = isset($_POST['showArrow']);
                $page['cards'][$index]['layout'] = in_array($page['cards'][$index]['layout'], ['compact', 'cover', 'button', 'mini'], true)
                    ? $page['cards'][$index]['layout'] : 'mini';
                header('Content-Type: text/html; charset=utf-8');
                (new PublicView())->render($page, true);
                return;
            }
            throw new InvalidArgumentException('Card não encontrado. Atualize o painel e tente novamente.');
        }

        if (array_key_exists('name', $_POST) || array_key_exists('avatar', $_POST) || array_key_exists('avatar_file', $_FILES)) {
            foreach (['name' => 120, 'title' => 160, 'slug' => 64, 'description' => 2000, 'avatar' => 2048] as $key => $limit) {
                $page[$key] = $this->liveValue($key, $limit, (string) ($page[$key] ?? ''));
            }
            $previewImage = $this->liveValue('avatar_preview_data', 2_800_100);
            if ($previewImage !== '') {
                $page['avatar'] = $previewImage;
            } elseif (isset($_POST['remove_avatar'])) {
                $page['avatar'] = '';
            }
        } elseif (array_key_exists('themePreset', $_POST) || array_key_exists('background', $_POST)) {
            if (isset($_POST['themePreset'])) {
                $preset = AdminView::templateThemes()[$this->liveValue('themePreset', 20)] ?? null;
                if ($preset === null) {
                    throw new InvalidArgumentException('Tema não encontrado.');
                }
                $page['theme'] = $preset;
            } else {
                foreach (['background', 'accent', 'text', 'cardBackground', 'font', 'radius', 'style', 'buttonStyle', 'titleSize', 'backgroundType', 'backgroundGradient', 'backgroundImage', 'colorScheme', 'cardSize', 'cardAlign'] as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $page['theme'][$key] = $this->liveValue($key, 400, (string) ($page['theme'][$key] ?? ''));
                    }
                }
            }
        } elseif (array_key_exists('social_instagram', $_POST) || array_key_exists('social_youtube', $_POST)) {
            $page['socials'] = [];
            foreach (['instagram', 'youtube', 'whatsapp', 'facebook', 'website', 'email'] as $platform) {
                $url = $this->liveValue('social_' . $platform, 2048);
                if ($url !== '') {
                    $page['socials'][] = ['platform' => $platform, 'url' => $url];
                }
            }
        } else {
            throw new InvalidArgumentException('Não há alterações para pré-visualizar.');
        }

        header('Content-Type: text/html; charset=utf-8');
        (new PublicView())->render($page, true);
    }

    private function liveValue(string $field, int $maxLength, string $fallback = ''): string
    {
        $value = $_POST[$field] ?? null;
        if (!is_string($value)) {
            return $fallback;
        }
        return mb_substr(trim($value), 0, $maxLength, 'UTF-8');
    }

    private function adminPost(string $path): void
    {
        if ($path === '/admin/new') {
            $theme = AdminView::templateThemes()[(string) ($_POST['template'] ?? 'natural')] ?? AdminView::templateThemes()['natural'];
            $page = $this->pages->create([
                'name' => trim((string) ($_POST['name'] ?? '')),
                'title' => trim((string) ($_POST['name'] ?? '')),
                'slug' => trim((string) ($_POST['slug'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'status' => 'draft', 'theme' => $theme, 'cards' => [], 'socials' => [], 'avatar' => '',
            ]);
            redirect('/admin/pages/' . $page['id'] . '?tab=links&notice=created');
        }
        if (!preg_match('~^/admin/pages/([a-f0-9]{16})$~', $path, $match)) {
            throw new RuntimeException('Operação não encontrada.', 404);
        }
        $page = $this->page($match[1]);
        $action = (string) ($_POST['action'] ?? '');
        $return = '/admin/pages/' . $page['id'];
        if ($action === 'duplicate') {
            $copy = $this->pages->duplicate($page['id']);
            redirect('/admin/pages/' . $copy['id'] . '?notice=duplicated');
        }
        if ($action === 'delete') {
            if (trim((string) ($_POST['confirmation'] ?? '')) !== $page['name']) {
                throw new InvalidArgumentException('Digite o nome da página exatamente para confirmar a exclusão.');
            }
            $this->pages->delete($page['id']);
            redirect('/admin?notice=deleted');
        }
        if ($action === 'status') {
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['published', 'draft', 'inactive'], true)) {
                throw new InvalidArgumentException('Escolha um estado válido para a página.');
            }
            $this->pages->update($page['id'], ['status' => $status]);
            redirect($return . '?notice=' . $status);
        }
        if ($action === 'details') {
            $avatar = trim((string) ($_POST['avatar'] ?? ''));
            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $avatar = (new MediaRepository())->store($_FILES['avatar_file']);
            }
            if (isset($_POST['remove_avatar'])) {
                $avatar = '';
            }
            $this->pages->update($page['id'], [
                'name' => trim((string) ($_POST['name'] ?? '')),
                'title' => trim((string) ($_POST['title'] ?? '')),
                'slug' => trim((string) ($_POST['slug'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'avatar' => $avatar,
            ]);
            redirect($return . '?tab=details&notice=saved');
        }
        if ($action === 'appearance') {
            $theme = $page['theme'];
            $uploadedBgImage = null;

            // Upload de arquivo de imagem de fundo — processa antes do foreach para não ser sobrescrito
            if (isset($_FILES['backgroundImage_file']) && $_FILES['backgroundImage_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedBgImage = (new MediaRepository())->store($_FILES['backgroundImage_file']);
            }
            if (isset($_POST['remove_backgroundImage'])) {
                $theme['backgroundImage'] = '';
                $theme['backgroundType'] = 'solid';
            }

            foreach (['background', 'accent', 'text', 'cardBackground', 'font', 'radius', 'style', 'buttonStyle', 'titleSize', 'backgroundType', 'backgroundGradient', 'backgroundImage', 'colorScheme', 'cardSize', 'cardAlign'] as $key) {
                if (array_key_exists($key, $_POST)) {
                    $theme[$key] = (string) ($_POST[$key] ?? $theme[$key] ?? '');
                }
            }

            // Aplicar o upload depois do foreach — garante que não é sobrescrito pelo POST vazio
            if ($uploadedBgImage !== null) {
                $theme['backgroundImage'] = $uploadedBgImage;
                $theme['backgroundType'] = 'image';
            }

            if (!empty($_POST['themePreset'])) {
                $preset = AdminView::templateThemes()[(string) $_POST['themePreset']] ?? null;
                if (!$preset) {
                    throw new InvalidArgumentException('Tema não encontrado.');
                }
                $theme = $preset;
            }
            $this->pages->update($page['id'], ['theme' => $theme]);
            redirect($return . '?tab=appearance&notice=saved');
        }
        if ($action === 'socials') {
            $socials = [];
            foreach (['instagram', 'youtube', 'whatsapp', 'facebook', 'website', 'email'] as $platform) {
                $url = trim((string) ($_POST['social_' . $platform] ?? ''));
                if ($url !== '') {
                    $socials[] = ['platform' => $platform, 'url' => $url];
                }
            }
            $this->pages->update($page['id'], ['socials' => $socials]);
            redirect($return . '?tab=socials&notice=saved');
        }
        $cards = $page['cards'];
        if ($action === 'card-add') {
            $type = (string) ($_POST['type'] ?? 'link');
            if (!in_array($type, ['link', 'image', 'heading', 'text', 'maps', 'photo'], true)) {
                throw new InvalidArgumentException('Tipo de card inválido.');
            }
            $new = [
                'id' => bin2hex(random_bytes(8)), 'type' => $type,
                'title' => ['link' => 'Novo link', 'image' => 'Novo card com imagem', 'heading' => 'Nova seção', 'text' => 'Uma mensagem para você', 'maps' => 'Como nos encontrar', 'photo' => ''][$type],
                'description' => '', 'url' => '', 'image' => '', 'layout' => 'mini', 'cta' => 'Acessar', 'icon' => $type === 'maps' ? 'map' : 'link',
                'color' => $page['theme']['cardBackground'] ?? '#ffffff', 'textColor' => $page['theme']['text'] ?? '#233b35',
                'titleWeight' => '', 'titleFontSize' => '', 'titleColor' => '', 'descColor' => '',
                'cardItemSize' => '', 'cardItemAlign' => '', 'cardOpacity' => 100,
                'embedSrc' => '', 'photoSize' => 'md',
                'enabled' => in_array($type, ['heading', 'text'], true),
                'newTab' => true, 'highlight' => false, 'showArrow' => true, 'badge' => '',
            ];
            $cards[] = $new;
            $this->pages->update($page['id'], ['cards' => $cards]);
            redirect($return . '?card=' . $new['id'] . '&notice=card-added');
        }
        $cardId = (string) ($_POST['card_id'] ?? '');
        $index = null;
        foreach ($cards as $position => $card) {
            if ($card['id'] === $cardId) {
                $index = $position;
                break;
            }
        }
        if ($index === null) {
            throw new InvalidArgumentException('Card não encontrado.');
        }
        if ($action === 'card-save') {
            $card = $cards[$index];
            foreach (['title', 'description', 'url', 'image', 'layout', 'cta', 'icon', 'color', 'textColor', 'badge', 'titleWeight', 'titleFontSize', 'titleColor', 'descColor', 'cardItemSize', 'cardItemAlign', 'photoSize'] as $key) {
                $card[$key] = trim((string) ($_POST[$key] ?? ''));
            }
            $card['cardOpacity'] = max(0, min(100, (int) ($_POST['cardOpacity'] ?? 100)));
            // Extract src from <iframe ...> embed code pasted by user
            if ($card['type'] === 'maps') {
                $raw = trim((string) ($_POST['embedSrc'] ?? ''));
                // If user pasted full <iframe ...> tag, extract the src attribute
                if (str_contains($raw, '<iframe') || str_contains($raw, '<IFRAME')) {
                    preg_match('/\bsrc=["\']([^"\']+)["\']/', $raw, $m);
                    $raw = $m[1] ?? '';
                }
                $card['embedSrc'] = $raw;
            } else {
                $card['embedSrc'] = '';
            }
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $card['image'] = (new MediaRepository())->store($_FILES['image_file']);
            }
            if (isset($_POST['remove_image'])) {
                $card['image'] = '';
            }
            foreach (['enabled', 'newTab', 'highlight'] as $key) {
                $card[$key] = isset($_POST[$key]);
            }
            $card['showArrow'] = isset($_POST['showArrow']);
            $cards[$index] = $card;
        } elseif ($action === 'card-delete') {
            array_splice($cards, $index, 1);
        } elseif ($action === 'card-move') {
            $direction = (string) ($_POST['direction'] ?? '');
            if (!in_array($direction, ['up', 'down'], true)) {
                throw new InvalidArgumentException('Direção inválida.');
            }
            $to = $index + ($direction === 'up' ? -1 : 1);
            if ($to >= 0 && $to < count($cards)) {
                [$cards[$index], $cards[$to]] = [$cards[$to], $cards[$index]];
            }
        } elseif ($action === 'card-toggle') {
            $cards[$index]['enabled'] = !$cards[$index]['enabled'];
        } elseif ($action === 'card-duplicate') {
            $copy = $cards[$index];
            $copy['id'] = bin2hex(random_bytes(8));
            $copy['title'] = mb_substr($copy['title'], 0, 152) . ' — cópia';
            array_splice($cards, $index + 1, 0, [$copy]);
        } else {
            throw new InvalidArgumentException('Operação inválida.');
        }
        $this->pages->update($page['id'], ['cards' => $cards]);
        redirect($return . '?notice=saved' . ($action === 'card-save' ? '&card=' . $cardId : ''));
    }

    private function protect(): void
    {
        Auth::requireUser();
        if (!Auth::verifyCsrf((string) ($_POST['_csrf'] ?? ''))) {
            throw new RuntimeException('A sessão deste formulário expirou. Atualize a página e tente novamente.', 403);
        }
    }

    private function page(string $id): array
    {
        return $this->pages->find($id) ?? throw new RuntimeException('Página não encontrada.', 404);
    }

    private function card(array $page, string $id): array
    {
        foreach ($page['cards'] as $card) {
            if ($card['id'] === $id) {
                return $card;
            }
        }
        throw new RuntimeException('Card não encontrado.', 404);
    }

    private function safeBack(string $path): string
    {
        if (preg_match('~^/admin/pages/([a-f0-9]{16})~', $path, $match)) {
            return '/admin/pages/' . $match[1];
        }
        return str_starts_with($path, '/admin') ? '/admin' : '/';
    }
}
