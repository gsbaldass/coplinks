<?php

declare(strict_types=1);

namespace Bio;

if (!function_exists(__NAMESPACE__ . '\\e')) {
    /**
     * Escape a scalar value for HTML text and attributes.
     */
    function e(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_string($value) && !is_numeric($value)) {
            $value = '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

final class PublicView
{
    /**
     * Render one public page as a complete HTML document.
     *
     * Routing, authorization, persistence, and redirect tracking remain outside
     * this view. Published links point at the parent's tracking endpoint, while
     * preview links point directly at their validated destination.
     */
    public static function render(array $page, bool $preview = false): void
    {
        $theme = self::normaliseTheme($page['theme'] ?? []);
        $pageId = self::stringValue($page['id'] ?? '');
        $name = self::stringValue($page['name'] ?? '');
        $pageTitle = self::stringValue($page['title'] ?? '');
        $heading = $pageTitle !== '' ? $pageTitle : ($name !== '' ? $name : 'Página de links');
        $documentTitle = $pageTitle !== '' ? $pageTitle : ($name !== '' ? $name : 'CopLinks');
        $description = self::stringValue($page['description'] ?? '');
        $avatar = self::assetUrl($page['avatar'] ?? '', $preview);
        $slug = self::stringValue($page['slug'] ?? '');
        $origin = Config::publicOrigin();
        $publicUrl = $origin !== '' && $slug !== '' ? $origin . '/' . rawurlencode($slug) : '';
        $shareImage = $avatar !== '' && str_starts_with($avatar, '/media/')
            ? ($origin !== '' ? $origin . $avatar : '') : $avatar;

        $cards = [];
        if (is_array($page['cards'] ?? null)) {
            foreach ($page['cards'] as $card) {
                if (is_array($card) && self::truthy($card['enabled'] ?? false)) {
                    $cards[] = $card;
                }
            }
        }

        $pageStyle = implode(';', [
            '--bio-background:'      . $theme['background'],
            '--bio-accent:'          . $theme['accent'],
            '--bio-text:'            . $theme['text'],
            '--bio-card-background:' . $theme['cardBackground'],
            '--bio-font-family:'     . $theme['fontFamily'],
            '--bio-radius:'          . $theme['radius'],
            '--bio-title-size:'      . $theme['titleSize'],
            '--bio-card-padding:'    . $theme['cardPadding'],
        ]);

        $avatarAlt = $name !== '' ? 'Foto de ' . $name : 'Avatar da página';
        $safeSlug = $slug !== '' ? $slug : $pageId;

        echo '<!doctype html>' . PHP_EOL;
        echo '<html lang="pt-BR">' . PHP_EOL;
        echo '<head>' . PHP_EOL;
        echo '<meta charset="utf-8">' . PHP_EOL;
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
        echo '<title>' . e($documentTitle) . '</title>' . PHP_EOL;
        if ($description !== '') {
            echo '<meta name="description" content="' . e($description) . '">' . PHP_EOL;
        }
        echo '<meta property="og:title" content="' . e($documentTitle) . '">' . PHP_EOL;
        if ($description !== '') {
            echo '<meta property="og:description" content="' . e($description) . '">' . PHP_EOL;
        }
        if (!$preview && $publicUrl !== '') {
            echo '<meta property="og:url" content="' . e($publicUrl) . '">' . PHP_EOL;
            echo '<link rel="canonical" href="' . e($publicUrl) . '">' . PHP_EOL;
        }
        echo '<meta property="og:site_name" content="CopLinks">' . PHP_EOL;
        echo '<meta name="twitter:card" content="summary">' . PHP_EOL;
        echo '<meta name="twitter:title" content="' . e($documentTitle) . '">' . PHP_EOL;
        echo '<meta name="twitter:description" content="' . e($description) . '">' . PHP_EOL;
        if ($shareImage !== '') {
            echo '<meta property="og:image" content="' . e($shareImage) . '">' . PHP_EOL;
            echo '<meta name="twitter:image" content="' . e($shareImage) . '">' . PHP_EOL;
        }
        if ($preview) {
            echo '<meta name="robots" content="noindex,nofollow">' . PHP_EOL;
        }
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL;
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL;
        echo '<link rel="preconnect" href="https://fonts.cdnfonts.com" crossorigin>' . PHP_EOL;
        echo '<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/garet?styles=17816">' . PHP_EOL;
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">' . PHP_EOL;
        echo '<link rel="icon" type="image/svg+xml" href="/assets/mark.svg">' . PHP_EOL;
        echo '<meta property="og:type" content="profile">' . PHP_EOL;
        echo '<link rel="stylesheet" href="/assets/public.css">' . PHP_EOL;
        echo '</head>' . PHP_EOL;
        echo '<body>' . PHP_EOL;
        echo '<div class="bio-page" data-style="' . e($theme['style']) . '" data-radius="' . e($theme['radiusName']) . '" data-button-style="' . e($theme['buttonStyle']) . '" data-bg-type="' . e($theme['backgroundType']) . '" data-scheme="' . e($theme['colorScheme']) . '" data-card-align="' . e($theme['cardAlign']) . '" data-card-size="' . e($theme['cardSize']) . '"';
        if ($safeSlug !== '') {
            echo ' data-page="' . e($safeSlug) . '"';
        }
        // Inline background overrides for gradient and image types
        $bgInline = '';
        if ($theme['backgroundType'] === 'gradient' && $theme['backgroundGradient'] !== '') {
            $bgInline = 'background:' . e($theme['backgroundGradient']) . ';';
        } elseif ($theme['backgroundType'] === 'image' && $theme['backgroundImage'] !== '') {
            $bgInline = 'background-image:url(' . e($theme['backgroundImage']) . ');background-size:cover;background-position:center;background-attachment:fixed;';
        }
        echo ' style="' . e($pageStyle) . ($bgInline !== '' ? ';' . $bgInline : '') . '">' . PHP_EOL;
        echo '<div class="bio-page__mesh" aria-hidden="true"></div>' . PHP_EOL;
        echo '<main class="bio-shell">' . PHP_EOL;

        if ($preview) {
            $previewLabel = !empty($page['_live_preview'])
                ? 'Prévia · alterações ainda não salvas'
                : (($page['status'] ?? '') === 'published' ? 'Prévia · versão salva' : 'Prévia · não publicada');
            echo '<div class="bio-preview-badge" role="status">' . e($previewLabel) . '</div>' . PHP_EOL;
        }

        echo '<header class="bio-profile">' . PHP_EOL;
        echo '<div class="bio-profile__avatar">';
        if ($avatar !== '') {
            echo '<img src="' . e($avatar) . '" alt="' . e($avatarAlt) . '" loading="lazy" decoding="async">';
        } else {
            echo '<span class="bio-profile__monogram">' . e(self::monogram($name !== '' ? $name : $heading)) . '</span>';
        }
        echo '</div>' . PHP_EOL;
        echo '<div class="bio-profile__copy">' . PHP_EOL;
        echo '<h1>' . e($heading) . '</h1>' . PHP_EOL;
        if ($description !== '') {
            echo '<p class="bio-profile__description">' . e($description) . '</p>' . PHP_EOL;
        }
        echo '</div>' . PHP_EOL;
        echo '</header>' . PHP_EOL;

        if ($cards !== []) {
            echo '<section class="bio-cards" aria-label="Links">' . PHP_EOL;
            foreach ($cards as $card) {
                self::renderCard($card, $theme, $pageId, $preview);
            }
            echo '</section>' . PHP_EOL;
        } else {
            echo '<section class="bio-empty" aria-label="Links">' . PHP_EOL;
            echo '<p>Os links desta página estarão aqui em breve.</p>' . PHP_EOL;
            echo '</section>' . PHP_EOL;
        }

        self::renderSocials($page['socials'] ?? []);

        echo '<footer class="bio-footer">' . PHP_EOL;
        echo '<a href="/" class="bio-footer__link">Feito com CopLinks</a>' . PHP_EOL;
        echo '</footer>' . PHP_EOL;
        echo '</main>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
        echo '</body>' . PHP_EOL;
        echo '</html>' . PHP_EOL;
    }

    /** @return array{background:string,accent:string,text:string,cardBackground:string,fontFamily:string,radius:string,radiusName:string,style:string,buttonStyle:string,titleSize:string,backgroundType:string,backgroundGradient:string,backgroundImage:string} */
    private static function normaliseTheme(mixed $value): array
    {
        $theme = is_array($value) ? $value : [];

        $fonts = [
            'sans'  => '"Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'serif' => 'Georgia, "Times New Roman", serif',
            'mono'  => '"SFMono-Regular", Consolas, "Liberation Mono", monospace',
        ];
        $fontName = self::stringValue($theme['font'] ?? 'sans');
        if (!isset($fonts[$fontName])) {
            $fontName = 'sans';
        }

        $radii = [
            'rounded' => '24px',
            'soft' => '12px',
            'square' => '3px',
        ];
        $radiusName = self::stringValue($theme['radius'] ?? 'rounded');
        if (!isset($radii[$radiusName])) {
            $radiusName = 'rounded';
        }

        $styles = ['solid', 'gradient', 'dotted'];
        $style = self::stringValue($theme['style'] ?? 'solid');
        if (!in_array($style, $styles, true)) {
            $style = 'solid';
        }

        $buttonStyles = ['filled', 'outline'];
        $buttonStyle = self::stringValue($theme['buttonStyle'] ?? 'filled');
        if (!in_array($buttonStyle, $buttonStyles, true)) {
            $buttonStyle = 'filled';
        }

        $titleSizes = [
            'sm'  => 'clamp(1.2rem,5vw,1.55rem)',
            'md'  => 'clamp(1.45rem,6vw,1.9rem)',
            'lg'  => 'clamp(1.65rem,7vw,2.2rem)',
            'xl'  => 'clamp(1.9rem,8vw,2.6rem)',
            '2xl' => 'clamp(2.2rem,10vw,3.2rem)',
        ];
        $titleSize = self::stringValue($theme['titleSize'] ?? 'xl');
        if (!isset($titleSizes[$titleSize])) {
            $titleSize = 'xl';
        }

        $backgroundTypes = ['solid', 'gradient', 'image'];
        $backgroundType = self::stringValue($theme['backgroundType'] ?? 'solid');
        if (!in_array($backgroundType, $backgroundTypes, true)) {
            $backgroundType = 'solid';
        }

        $backgroundGradient = self::stringValue($theme['backgroundGradient'] ?? '');
        $backgroundImage    = self::safeUrl($theme['backgroundImage'] ?? '', true); // true: aceita /media/<id>

        $colorScheme = self::stringValue($theme['colorScheme'] ?? 'light');
        if (!in_array($colorScheme, ['light', 'dark'], true)) {
            $colorScheme = 'light';
        }

        $cardSizePadding = [
            'xs' => '8px 10px',
            'sm' => '10px 12px',
            'md' => '14px 15px',
            'lg' => '18px 19px',
            'xl' => '24px 26px',
        ];
        $cardSize = self::stringValue($theme['cardSize'] ?? 'md');
        if (!isset($cardSizePadding[$cardSize])) {
            $cardSize = 'md';
        }

        $cardAlign = self::stringValue($theme['cardAlign'] ?? 'left');
        if (!in_array($cardAlign, ['left', 'center', 'right'], true)) {
            $cardAlign = 'left';
        }

        return [
            'background'         => self::hexColor($theme['background'] ?? '', '#f5f1eb'),
            'accent'             => self::hexColor($theme['accent'] ?? '', '#147d69'),
            'text'               => self::hexColor($theme['text'] ?? '', '#183b34'),
            'cardBackground'     => self::hexColor($theme['cardBackground'] ?? '', '#fffdf9'),
            'fontFamily'         => $fonts[$fontName],
            'radius'             => $radii[$radiusName],
            'radiusName'         => $radiusName,
            'style'              => $style,
            'buttonStyle'        => $buttonStyle,
            'titleSize'          => $titleSizes[$titleSize],
            'titleSizeName'      => $titleSize,
            'backgroundType'     => $backgroundType,
            'backgroundGradient' => $backgroundGradient,
            'backgroundImage'    => $backgroundImage,
            'colorScheme'        => $colorScheme,
            'cardSize'           => $cardSize,
            'cardPadding'        => $cardSizePadding[$cardSize],
            'cardAlign'          => $cardAlign,
        ];
    }

    private static function renderCard(array $card, array $theme, string $pageId, bool $preview): void
    {
        if (!self::truthy($card['enabled'] ?? false)) {
            return;
        }

        $type = strtolower(self::stringValue($card['type'] ?? 'link'));
        if (!in_array($type, ['link', 'image', 'heading', 'text', 'maps', 'photo'], true)) {
            $type = 'link';
        }

        $title = self::stringValue($card['title'] ?? '');
        $description = self::stringValue($card['description'] ?? '');
        $badge = self::stringValue($card['badge'] ?? '');
        $cta = self::stringValue($card['cta'] ?? '');
        if ($cta === '') {
            $cta = 'Acessar';
        }
        $layout = strtolower(self::stringValue($card['layout'] ?? 'compact'));
        if (!in_array($layout, ['compact', 'cover', 'button', 'mini', 'banner'], true)) {
            $layout = 'compact';
        }
        $cardId      = self::stringValue($card['id'] ?? '');
        $destination = self::safeUrl($card['url'] ?? '', true);
        $href        = self::cardHref($destination, $pageId, $cardId, $preview);
        $image       = self::assetUrl($card['image'] ?? '', $preview);
        $icon        = self::normaliseIcon($card['icon'] ?? 'link');
        $accent      = self::hexColor($card['color'] ?? '', $theme['accent']);
        $cardText    = self::hexColor($card['textColor'] ?? '', $theme['text']);

        // Per-card typography overrides
        $titleWeight   = self::stringValue($card['titleWeight'] ?? '');
        $titleFontSize = self::stringValue($card['titleFontSize'] ?? '');
        $titleColor    = self::hexColor($card['titleColor'] ?? '', '');
        $descColor     = self::hexColor($card['descColor'] ?? '', '');

        $fontSizeMap = [
            'xs' => '0.78rem', 'sm' => '0.88rem', 'md' => '1rem',
            'lg' => '1.12rem', 'xl' => '1.25rem',
        ];
        $validWeights = ['300', '400', '500', '600', '700', '800', '900'];

        // Per-card size and alignment — applied as CSS vars on the card element
        $cardItemSize  = self::stringValue($card['cardItemSize'] ?? '');
        $cardItemAlign = self::stringValue($card['cardItemAlign'] ?? '');
        $sizeMinHeights = ['xs' => '46px','sm' => '58px','md' => '','lg' => '86px','xl' => '104px'];
        $sizePaddings   = ['xs' => '7px 10px','sm' => '9px 12px','md' => '','lg' => '17px 19px','xl' => '22px 26px'];
        $sizeThumbs     = ['xs' => '34px','sm' => '44px','md' => '','lg' => '64px','xl' => '78px'];
        $sizeMiniThumbs = ['xs' => '26px','sm' => '34px','md' => '','lg' => '50px','xl' => '60px'];
        $sizeTitleFs    = ['xs' => '0.78rem','sm' => '0.88rem','md' => '','lg' => '1.08rem','xl' => '1.2rem'];
        $sizeArrows     = ['xs' => '26px','sm' => '30px','md' => '','lg' => '40px','xl' => '44px'];

        $classes = ['bio-card', 'bio-card--' . $type];
        if ($type === 'link' || $type === 'image') {
            $classes[] = 'bio-card--layout-' . $layout;
        }
        if ($type === 'maps') {
            $classes[] = 'bio-card--maps';
        }
        if (self::truthy($card['highlight'] ?? false)) {
            $classes[] = 'bio-card--highlight';
        }
        if ($href !== '') {
            $classes[] = 'bio-card--linked';
        }

        $cardStyle = '--bio-card-accent:' . $accent
            . ';--bio-card-text:' . $cardText
            . ';--bio-card-background:' . $accent
            . ';--bio-card-surface:' . $accent;
        if ($titleWeight !== '' && in_array($titleWeight, $validWeights, true)) {
            $cardStyle .= ';--bio-card-title-weight:' . $titleWeight;
        }
        if ($titleFontSize !== '' && isset($fontSizeMap[$titleFontSize])) {
            $cardStyle .= ';--bio-card-title-size:' . $fontSizeMap[$titleFontSize];
        }
        if ($titleColor !== '') {
            $cardStyle .= ';--bio-card-title-color:' . $titleColor;
        }
        if ($descColor !== '') {
            $cardStyle .= ';--bio-card-desc-color:' . $descColor;
        }
        // Per-card size overrides via CSS vars
        if ($cardItemSize !== '' && isset($sizePaddings[$cardItemSize])) {
            if ($sizePaddings[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-padding:' . $sizePaddings[$cardItemSize];
            }
            if ($sizeMinHeights[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-min-height:' . $sizeMinHeights[$cardItemSize];
            }
            if ($sizeThumbs[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-thumb:' . $sizeThumbs[$cardItemSize];
            }
            if ($sizeMiniThumbs[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-mini-thumb:' . $sizeMiniThumbs[$cardItemSize];
            }
            if ($sizeTitleFs[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-title-fs:' . $sizeTitleFs[$cardItemSize];
            }
            if ($sizeArrows[$cardItemSize] !== '') {
                $cardStyle .= ';--bio-item-arrow:' . $sizeArrows[$cardItemSize];
            }
        }
        // Per-card align override
        if ($cardItemAlign !== '') {
            $cardStyle .= ';--bio-item-align:' . $cardItemAlign;
        }
        // Per-card background opacity — gera background rgba diretamente (CSS var + calc não funciona em color-mix)
        $cardOpacity = (int) ($card['cardOpacity'] ?? 100);
        if ($cardOpacity < 0)   $cardOpacity = 0;
        if ($cardOpacity > 100) $cardOpacity = 100;
        if ($cardOpacity < 100) {
            // Converte a cor hex do card background para rgba com alpha
            $bgHex = ltrim(self::hexColor($card['color'] ?? '', $theme['cardBackground']), '#');
            if (strlen($bgHex) === 6) {
                $r = hexdec(substr($bgHex, 0, 2));
                $g = hexdec(substr($bgHex, 2, 2));
                $b = hexdec(substr($bgHex, 4, 2));
                $alpha = round($cardOpacity / 100, 2);
                $cardStyle .= ';background:rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
            }
        }

        // Per-card arrow visibility (default true)
        $showArrow = self::truthy($card['showArrow'] ?? true);

        if ($type === 'heading') {
            echo '<article class="' . e(implode(' ', $classes)) . '" style="' . e($cardStyle) . '">' . PHP_EOL;
            if ($title !== '') {
                echo '<h2 class="bio-card__heading">' . e($title) . '</h2>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<p class="bio-card__description">' . e($description) . '</p>' . PHP_EOL;
            }
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '</article>' . PHP_EOL;
            return;
        }

        if ($type === 'text') {
            echo '<article class="' . e(implode(' ', $classes)) . '" style="' . e($cardStyle) . '">' . PHP_EOL;
            if ($title !== '') {
                echo '<h2 class="bio-card__heading">' . e($title) . '</h2>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<p class="bio-card__description">' . e($description) . '</p>' . PHP_EOL;
            }
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '</article>' . PHP_EOL;
            return;
        }

        if ($type === 'maps') {
            // Aceita: embedSrc (iframe colado), url (qualquer link do Maps ou endereço)
            $rawInput  = self::stringValue($card['embedSrc'] ?? '');
            if ($rawInput === '') {
                $rawInput = $destination; // campo url do card
            }
            $embedUrl = self::mapsEmbedUrl($rawInput);
            // Link externo para "Abrir no Maps" — usa a URL original, não o embed
            $mapsHref = $destination !== '' ? $destination : ($rawInput !== '' ? $rawInput : '');
            $newTabAttr = self::truthy($card['newTab'] ?? false) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $ctaText   = self::stringValue($card['cta'] ?? 'Abrir no Maps');
            if ($ctaText === '' || $ctaText === 'Acessar') {
                $ctaText = 'Abrir no Maps';
            }
            echo '<article class="' . e(implode(' ', $classes)) . '" style="' . e($cardStyle) . '">' . PHP_EOL;

            // ── Mapa no topo ──────────────────────────────────────
            if ($embedUrl !== '') {
                echo '<div class="bio-card__maps-frame">' . PHP_EOL;
                echo '<iframe src="' . e($embedUrl) . '" title="' . e($title !== '' ? $title : 'Mapa') . '" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>' . PHP_EOL;
                echo '</div>' . PHP_EOL;
            } else {
                echo '<div class="bio-card__maps-placeholder" aria-hidden="true">' . self::iconSvg('map', 38) . '</div>' . PHP_EOL;
            }

            // ── Corpo: título + descrição ─────────────────────────
            echo '<div class="bio-card__maps-body">' . PHP_EOL;
            if ($title !== '') {
                echo '<span class="bio-card__maps-title">' . e($title) . '</span>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<span class="bio-card__maps-desc">' . e($description) . '</span>' . PHP_EOL;
            }
            echo '</div>' . PHP_EOL;

            // ── Rodapé: botão(ões) de ação ────────────────────────
            if ($mapsHref !== '') {
                echo '<div class="bio-card__maps-actions">' . PHP_EOL;
                echo '<a class="bio-card__maps-btn bio-card__maps-btn--primary" href="' . e($mapsHref) . '" target="_blank" rel="noopener noreferrer">' . e($ctaText) . '</a>' . PHP_EOL;
                if ($badge !== '') {
                    echo '<a class="bio-card__maps-btn bio-card__maps-btn--secondary" href="' . e($mapsHref) . '" target="_blank" rel="noopener noreferrer">' . e($badge) . '</a>' . PHP_EOL;
                }
                echo '</div>' . PHP_EOL;
            } elseif ($badge !== '') {
                echo '<div class="bio-card__maps-actions">' . PHP_EOL;
                echo '<span class="bio-card__maps-btn bio-card__maps-btn--secondary">' . e($badge) . '</span>' . PHP_EOL;
                echo '</div>' . PHP_EOL;
            }

            echo '</article>' . PHP_EOL;
            return;
        }

        // ── Tipo photo — só imagem, sem card, fundo transparente ──
        if ($type === 'photo') {
            $photoSize = self::stringValue($card['photoSize'] ?? 'md');
            $photoHeights = [
                'xs'   => '60px',
                'sm'   => '80px',
                'md'   => '120px',
                'lg'   => '180px',
                'xl'   => '240px',
                'full' => 'auto',
            ];
            if (!isset($photoHeights[$photoSize])) {
                $photoSize = 'md';
            }
            $heightStyle = '--photo-h:' . $photoHeights[$photoSize];
            $newTabAttr = self::truthy($card['newTab'] ?? false) ? ' target="_blank" rel="noopener noreferrer"' : '';

            if ($href !== '') {
                echo '<a class="bio-card--photo" style="' . e($heightStyle) . '" href="' . e($href) . '"' . $newTabAttr . ' aria-label="' . e($title !== '' ? $title : 'Imagem') . '">' . PHP_EOL;
            } else {
                echo '<span class="bio-card--photo" style="' . e($heightStyle) . '">' . PHP_EOL;
            }
            if ($image !== '') {
                echo '<img src="' . e($image) . '" alt="' . e($title) . '" loading="lazy" decoding="async">' . PHP_EOL;
            } else {
                echo '<span class="bio-card--photo__placeholder" aria-hidden="true">' . self::iconSvg('image', 32) . '</span>' . PHP_EOL;
            }
            echo '</' . ($href !== '' ? 'a' : 'span') . '>' . PHP_EOL;
            return;
        }

        $tag = $href !== '' ? 'a' : 'article';
        echo '<' . $tag . ' class="' . e(implode(' ', $classes)) . '" style="' . e($cardStyle) . '"';
        if ($tag === 'a') {
            echo ' href="' . e($href) . '"';
            if (self::truthy($card['newTab'] ?? false)) {
                echo ' target="_blank" rel="noopener noreferrer"';
            }
        }
        echo '>' . PHP_EOL;

        if ($layout === 'cover' && ($type === 'link' || $type === 'image')) {
            echo '<span class="bio-card__cover">' . PHP_EOL;
            if ($image !== '') {
                echo '<img class="bio-card__cover-image" src="' . e($image) . '" alt="" loading="lazy" decoding="async">' . PHP_EOL;
            } else {
                echo '<span class="bio-card__cover-fallback" aria-hidden="true">' . self::iconSvg($icon, 42) . '</span>' . PHP_EOL;
            }
            echo '<span class="bio-card__cover-shade" aria-hidden="true"></span>' . PHP_EOL;
            echo '<span class="bio-card__cover-copy">' . PHP_EOL;
            if ($title !== '') {
                echo '<span class="bio-card__title">' . e($title) . '</span>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<span class="bio-card__description">' . e($description) . '</span>' . PHP_EOL;
            }
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            if ($href !== '' && $showArrow) {
                echo '<span class="bio-card__arrow bio-card__arrow--circle" aria-hidden="true">' . self::iconSvg('arrow', 18) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
        } elseif ($layout === 'button' && ($type === 'link' || $type === 'image')) {
            echo '<span class="bio-card__article-image">' . PHP_EOL;
            if ($image !== '') {
                echo '<img class="bio-card__image" src="' . e($image) . '" alt="' . e($title) . '" loading="lazy" decoding="async">' . PHP_EOL;
            } else {
                echo '<span class="bio-card__article-fallback" aria-hidden="true">' . self::iconSvg($icon, 42) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            echo '<span class="bio-card__article-body">' . PHP_EOL;
            if ($title !== '') {
                echo '<span class="bio-card__title">' . e($title) . '</span>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<span class="bio-card__description">' . e($description) . '</span>' . PHP_EOL;
            }
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '<span class="bio-card__cta">' . e($cta) . ' <span aria-hidden="true">↗</span></span>' . PHP_EOL;
            echo '</span>' . PHP_EOL;
        } elseif ($layout === 'mini' && ($type === 'link' || $type === 'image')) {
            // Mini layout: small square image (40px) + compact text + arrow — no extra chrome
            echo '<span class="bio-card__body bio-card__body--mini">' . PHP_EOL;
            echo '<span class="bio-card__main">' . PHP_EOL;
            if ($image !== '') {
                echo '<span class="bio-card__thumbnail bio-card__thumbnail--mini" aria-hidden="true"><img src="' . e($image) . '" alt="" loading="lazy" decoding="async"></span>' . PHP_EOL;
            } else {
                echo '<span class="bio-card__thumbnail bio-card__thumbnail--mini bio-card__thumbnail--fallback" aria-hidden="true">' . self::iconSvg($icon, 18) . '</span>' . PHP_EOL;
            }
            echo '<span class="bio-card__copy">' . PHP_EOL;
            if ($title !== '') {
                echo '<span class="bio-card__title">' . e($title) . '</span>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<span class="bio-card__description">' . e($description) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            if ($href !== '' && $showArrow) {
                echo '<span class="bio-card__arrow bio-card__arrow--circle bio-card__arrow--small" aria-hidden="true">' . self::iconSvg('arrow', 14) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
        } elseif ($layout === 'banner' && ($type === 'link' || $type === 'image')) {
            // Banner: imagem full-width, sem texto obrigatório, clicável
            if ($image !== '') {
                echo '<img class="bio-card__banner-img" src="' . e($image) . '" alt="' . e($title) . '" loading="lazy" decoding="async">' . PHP_EOL;
            } else {
                echo '<span class="bio-card__banner-placeholder" aria-hidden="true">' . self::iconSvg($icon, 32) . '</span>' . PHP_EOL;
            }
            if ($title !== '' || $description !== '') {
                echo '<span class="bio-card__banner-caption">' . PHP_EOL;
                if ($title !== '') {
                    echo '<span class="bio-card__title">' . e($title) . '</span>' . PHP_EOL;
                }
                if ($description !== '') {
                    echo '<span class="bio-card__description">' . e($description) . '</span>' . PHP_EOL;
                }
                echo '</span>' . PHP_EOL;
            }
        } else {
            echo '<span class="bio-card__body">' . PHP_EOL;
            echo '<span class="bio-card__main">' . PHP_EOL;
            if ($image !== '') {
                echo '<span class="bio-card__thumbnail" aria-hidden="true"><img src="' . e($image) . '" alt="" loading="lazy" decoding="async"></span>' . PHP_EOL;
            } elseif ($type === 'link' || $icon !== '') {
                echo '<span class="bio-card__thumbnail bio-card__thumbnail--fallback" aria-hidden="true">' . self::iconSvg($icon, 22) . '</span>' . PHP_EOL;
            }
            echo '<span class="bio-card__copy">' . PHP_EOL;
            if ($title !== '') {
                echo '<span class="bio-card__title">' . e($title) . '</span>' . PHP_EOL;
            }
            if ($description !== '') {
                echo '<span class="bio-card__description">' . e($description) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            if ($href !== '' && $showArrow) {
                echo '<span class="bio-card__arrow bio-card__arrow--circle" aria-hidden="true">' . self::iconSvg('arrow', 18) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
            if ($badge !== '') {
                echo '<span class="bio-card__badge">' . e($badge) . '</span>' . PHP_EOL;
            }
            echo '</span>' . PHP_EOL;
        }
        echo '</' . $tag . '>' . PHP_EOL;
    }

    private static function renderSocials(mixed $socials): void
    {
        if (!is_array($socials)) {
            return;
        }

        $items = [];
        foreach ($socials as $social) {
            if (!is_array($social)) {
                continue;
            }
            $platform = strtolower(self::stringValue($social['platform'] ?? ''));
            $url = self::safeUrl($social['url'] ?? '', false);
            if ($platform === '' || $url === '') {
                continue;
            }
            $items[] = [$platform, $url];
        }
        if ($items === []) {
            return;
        }

        echo '<nav class="bio-socials" aria-label="Redes sociais">' . PHP_EOL;
        echo '<ul>' . PHP_EOL;
        foreach ($items as [$platform, $url]) {
            $label = self::socialLabel($platform);
            $icon = self::socialIcon($platform);
            echo '<li>' . PHP_EOL;
            echo '<a class="bio-socials__link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e($label) . '">';
            echo '<span class="bio-socials__icon" aria-hidden="true">' . self::iconSvg($icon, 19) . '</span>';
            echo '<span class="visually-hidden">' . e($label) . '</span>';
            echo '</a>' . PHP_EOL;
            echo '</li>' . PHP_EOL;
        }
        echo '</ul>' . PHP_EOL;
        echo '</nav>' . PHP_EOL;
    }

    private static function cardHref(string $destination, string $pageId, string $cardId, bool $preview): string
    {
        if ($destination === '') {
            return '';
        }
        if ($preview) {
            return $destination;
        }
        if ($pageId === '' || $cardId === '') {
            return '';
        }

        return '/go/' . rawurlencode($pageId) . '/' . rawurlencode($cardId);
    }

    private static function assetUrl(mixed $value, bool $allowPreviewImage = false): string
    {
        if ($allowPreviewImage && is_string($value)) {
            $inlineImage = self::previewImageDataUrl($value);
            if ($inlineImage !== '') {
                return $inlineImage;
            }
        }

        $url = self::safeUrl($value, true);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['scheme']) && !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    private static function previewImageDataUrl(string $value): string
    {
        if (strlen($value) > 2_800_100 || preg_match('~^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/]+={0,2})$~D', $value, $match) !== 1) {
            return '';
        }
        $content = base64_decode($match[2], true);
        if (!is_string($content) || $content === '' || strlen($content) > 2_097_152) {
            return '';
        }
        $image = @getimagesizefromstring($content);
        $expectedMime = ['png' => 'image/png', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$match[1]];
        if (!is_array($image) || ($image['mime'] ?? '') !== $expectedMime) {
            return '';
        }
        return $value;
    }

    private static function safeUrl(mixed $value, bool $allowRelative): string
    {
        $url = trim(self::stringValue($value));
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\\x00-\\x20\\x7f]/', $url) === 1) {
            return '';
        }
        if (preg_match('/^(?:javascript|data|vbscript):/i', $url) === 1 || str_starts_with($url, '//')) {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== '') {
            if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                return '';
            }
            if (in_array($scheme, ['http', 'https'], true) && empty($parts['host'])) {
                return '';
            }
            return $url;
        }

        if (!$allowRelative) {
            return '';
        }

        if (str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._~\/-]*(?:\?[^ ]*)?(?:#[^ ]*)?$/', $url) === 1) {
            return $url;
        }

        return '';
    }

    private static function normaliseIcon(mixed $value): string
    {
        $icon = strtolower(self::stringValue($value));
        $allowed = ['link', 'globe', 'instagram', 'youtube', 'whatsapp', 'mail', 'calendar', 'map', 'heart', 'book', 'phone', 'arrow'];

        return in_array($icon, $allowed, true) ? $icon : 'link';
    }

    private static function socialIcon(string $platform): string
    {
        $icons = [
            'instagram' => 'instagram',
            'youtube' => 'youtube',
            'whatsapp' => 'whatsapp',
            'mail' => 'mail',
            'email' => 'map',
            'e-mail' => 'mail',
            'phone' => 'phone',
            'telefone' => 'phone',
            'globe' => 'globe',
            'site' => 'globe',
            'website' => 'globe',
            'linkedin' => 'link',
            'facebook' => 'link',
            'twitter' => 'link',
            'x' => 'link',
            'tiktok' => 'music',
        ];

        return self::normaliseIcon($icons[$platform] ?? 'globe');
    }

    private static function socialLabel(string $platform): string
    {
        $labels = [
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'whatsapp' => 'WhatsApp',
            'mail' => 'E-mail',
            'email' => 'Localização',
            'e-mail' => 'E-mail',
            'phone' => 'Telefone',
            'telefone' => 'Telefone',
            'globe' => 'Site',
            'site' => 'Site',
            'website' => 'Site',
            'linkedin' => 'LinkedIn',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            'x' => 'X',
            'tiktok' => 'TikTok',
        ];

        if (isset($labels[$platform])) {
            return $labels[$platform];
        }

        return ucfirst($platform);
    }

    private static function iconSvg(string $name, int $size = 20): string
    {
        $name = self::normaliseIcon($name);
        $size = max(12, min(48, $size));
        $helper = __NAMESPACE__ . '\\icon';
        if (function_exists($helper)) {
            $result = $helper($name, $size);
            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/></svg>';
    }

    /**
     * Converte qualquer link do Google Maps para uma URL de embed com iframe.
     * Aceita: embed direto, links completos, links curtos (goo.gl, maps.app, share.google).
     * Para links curtos resolve o redirect server-side para extrair coordenadas/place.
     */
    private static function mapsEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // 1. Já é um embed — usa direto
        if (str_contains($url, 'maps/embed') || str_contains($url, 'maps.google.com/maps?')) {
            return $url;
        }

        // 2. Contém código <iframe> — extrai o src
        if (str_contains($url, '<iframe')) {
            if (preg_match('/\bsrc=["\']([^"\']+)["\']/', $url, $m)) {
                return self::mapsEmbedUrl($m[1]); // recursão com o src extraído
            }
            return '';
        }

        // 3. Link completo do Google Maps (google.com/maps/...) — extrai coordenadas/place se possível
        if (str_contains($url, 'google.com/maps')) {
            // Tenta extrair @lat,lng do path
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
                return 'https://maps.google.com/maps?output=embed&q=' . $m[1] . ',' . $m[2] . '&z=16';
            }
            // Tenta extrair place do path
            if (preg_match('~/maps/place/([^/@?]+)~', $url, $m)) {
                return 'https://maps.google.com/maps?output=embed&q=' . rawurlencode(urldecode($m[1]));
            }
            // Fallback: usa a URL como query
            return 'https://maps.google.com/maps?output=embed&q=' . rawurlencode($url);
        }

        // 4. Links curtos (share.google, maps.app.goo.gl, goo.gl) — resolve server-side
        $shortDomains = ['share.google', 'maps.app.goo.gl', 'goo.gl', 'g.co'];
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        foreach ($shortDomains as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                $resolved = self::resolveShortUrl($url);
                if ($resolved !== '' && $resolved !== $url) {
                    return self::mapsEmbedUrl($resolved); // recursão com URL resolvida
                }
                // Não conseguiu resolver — usa como query direta
                return 'https://maps.google.com/maps?output=embed&q=' . rawurlencode($url);
            }
        }

        // 5. Qualquer URL https — tenta como query
        if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            return 'https://maps.google.com/maps?output=embed&q=' . rawurlencode($url);
        }

        // 6. Texto puro (endereço, nome de lugar) — usa como query
        if ($url !== '') {
            return 'https://maps.google.com/maps?output=embed&q=' . rawurlencode($url);
        }

        return '';
    }

    /**
     * Segue um redirect HTTP para resolver links curtos.
     * Faz apenas uma requisição HEAD para evitar baixar o body.
     */
    private static function resolveShortUrl(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return '';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_NOBODY         => true,   // HEAD only
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; CopLinks/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return ($finalUrl !== '' && $finalUrl !== $url) ? $finalUrl : '';
    }

    private static function hexColor(mixed $value, string $fallback): string
    {
        $color = trim(self::stringValue($value));
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1}|[0-9a-fA-F]{3}|[0-9a-fA-F]{5})?$/', $color) === 1) {
            return strtolower($color);
        }

        return $fallback;
    }

    private static function monogram(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'E';
        }

        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = [];
        foreach (array_slice($words, 0, 2) as $word) {
            $letter = function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
            if ($letter !== false && $letter !== '') {
                $letters[] = $letter;
            }
        }

        return strtoupper(implode('', $letters) ?: 'E');
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'false', 'no', 'off'], true);
        }

        return !empty($value);
    }

    private static function stringValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }
        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return '';
    }
}
