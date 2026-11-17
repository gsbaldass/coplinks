<?php
declare(strict_types=1);

namespace Bio;

use InvalidArgumentException;

final class PageValidator
{
    /** @var array<string,string> */
    private const DEFAULT_THEME = [
        'background'         => '#f5f1eb',
        'accent'             => '#147d69',
        'text'               => '#233b35',
        'cardBackground'     => '#ffffff',
        'font'               => 'sans',
        'radius'             => 'rounded',
        'style'              => 'solid',
        'buttonStyle'        => 'filled',
        'titleSize'          => 'xl',
        'backgroundType'     => 'solid',
        'backgroundGradient' => '',
        'backgroundImage'    => '',
        'colorScheme'        => 'light',
        'cardSize'           => 'md',
        'cardAlign'          => 'left',
    ];

    /** @var list<string> */
    private const RESERVED_SLUGS = [
        'admin', 'api', 'auth', 'login', 'health', 'assets', 'preview', 'midia', 'mídia', 'media', 'go',
    ];

    /** @var list<string> */
    private const ICONS = [
        'link', 'globe', 'instagram', 'youtube', 'whatsapp', 'mail', 'calendar', 'map', 'heart', 'book',
        'phone', 'arrow',
    ];

    /** @var list<string> */
    private const SOCIAL_PLATFORMS = ['instagram', 'youtube', 'whatsapp', 'facebook', 'website', 'email'];

    /**
     * Validate and normalize a complete page payload.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function validate(array $data): array
    {
        $slug = self::stringField($data, 'slug', 64, true);
        $slug = strtolower(trim($slug));
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('O slug deve conter apenas letras minúsculas, números e hífens.');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new InvalidArgumentException('Este slug é reservado.');
        }

        $name = self::stringField($data, 'name', 120, true);
        $title = self::stringField($data, 'title', 160, true);
        $description = self::stringField($data, 'description', 2000, false, '');
        $avatar = self::stringField($data, 'avatar', 2048, false, '');
        if ($avatar !== '' && !self::isHttpOrMediaUrl($avatar)) {
            throw new InvalidArgumentException('O avatar deve usar uma URL HTTP(S) ou uma mídia local válida.');
        }

        $status = self::stringField($data, 'status', 16, false, 'draft');
        if (!in_array($status, ['draft', 'published', 'inactive'], true)) {
            throw new InvalidArgumentException('O status da página é inválido.');
        }

        $theme = self::theme($data['theme'] ?? []);
        $cards = self::cards($data['cards'] ?? []);
        $socials = self::socials($data['socials'] ?? []);

        return [
            'slug' => $slug,
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'avatar' => $avatar,
            'status' => $status,
            'theme' => $theme,
            'cards' => $cards,
            'socials' => $socials,
        ];
    }

    /** @param array<string,mixed> $data */
    private static function stringField(
        array $data,
        string $field,
        int $maxLength,
        bool $required,
        ?string $default = null
    ): string {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            if ($required) {
                throw new InvalidArgumentException(sprintf('O campo %s é obrigatório.', $field));
            }
            return $default ?? '';
        }
        if (!is_string($data[$field])) {
            throw new InvalidArgumentException(sprintf('O campo %s deve ser texto.', $field));
        }
        $value = trim($data[$field]);
        if ($required && $value === '') {
            throw new InvalidArgumentException(sprintf('O campo %s é obrigatório.', $field));
        }
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidArgumentException(sprintf('O campo %s excede o limite de %d caracteres.', $field, $maxLength));
        }
        return $value;
    }

    /** @param mixed $value */
    private static function theme(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('O tema deve ser um objeto.');
        }

        $theme = self::DEFAULT_THEME;
        foreach (self::DEFAULT_THEME as $key => $default) {
            if (!array_key_exists($key, $value)) {
                continue;
            }
            if (!is_string($value[$key])) {
                throw new InvalidArgumentException(sprintf('O tema %s é inválido.', $key));
            }
            $theme[$key] = trim($value[$key]);
        }

        foreach (['background', 'accent', 'text', 'cardBackground'] as $key) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $theme[$key])) {
                throw new InvalidArgumentException(sprintf('A cor %s deve estar no formato hexadecimal.', $key));
            }
            $theme[$key] = strtolower($theme[$key]);
        }
        if (!in_array($theme['font'], ['sans', 'serif', 'mono'], true)) {
            throw new InvalidArgumentException('A fonte do tema é inválida.');
        }
        if (!in_array($theme['radius'], ['rounded', 'soft', 'square'], true)) {
            throw new InvalidArgumentException('O raio do tema é inválido.');
        }
        if (!in_array($theme['style'], ['solid', 'gradient', 'dotted'], true)) {
            throw new InvalidArgumentException('O estilo do tema é inválido.');
        }
        if (!in_array($theme['buttonStyle'], ['filled', 'outline'], true)) {
            throw new InvalidArgumentException('O estilo dos botões é inválido.');
        }
        if (!in_array($theme['titleSize'], ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
            $theme['titleSize'] = 'xl';
        }
        if (!in_array($theme['backgroundType'], ['solid', 'gradient', 'image'], true)) {
            $theme['backgroundType'] = 'solid';
        }
        // backgroundGradient: allow safe CSS gradient strings or empty
        if ($theme['backgroundGradient'] !== '' && mb_strlen($theme['backgroundGradient'], 'UTF-8') > 400) {
            $theme['backgroundGradient'] = '';
        }
        // backgroundImage: must be http/https URL or media path or empty
        if ($theme['backgroundImage'] !== '' && !self::isHttpOrMediaUrl($theme['backgroundImage'])) {
            $theme['backgroundImage'] = '';
        }
        if (!in_array($theme['colorScheme'], ['light', 'dark'], true)) {
            $theme['colorScheme'] = 'light';
        }
        if (!in_array($theme['cardSize'], ['xs', 'sm', 'md', 'lg', 'xl'], true)) {
            $theme['cardSize'] = 'md';
        }
        if (!in_array($theme['cardAlign'], ['left', 'center', 'right'], true)) {
            $theme['cardAlign'] = 'left';
        }

        return $theme;
    }

    /** @param mixed $value */
    private static function cards(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('Os cards devem ser uma lista.');
        }
        if (count($value) > 100) {
            throw new InvalidArgumentException('A página pode ter no máximo 100 cards.');
        }

        $cards = [];
        $ids = [];
        foreach ($value as $index => $rawCard) {
            if (!is_array($rawCard)) {
                throw new InvalidArgumentException(sprintf('O card %d é inválido.', $index + 1));
            }

            $type = $rawCard['type'] ?? 'link';
            if (!is_string($type) || !in_array($type, ['link', 'image', 'heading', 'text', 'maps', 'photo'], true)) {
                throw new InvalidArgumentException(sprintf('O tipo do card %d é inválido.', $index + 1));
            }

            $id = $rawCard['id'] ?? bin2hex(random_bytes(8));
            if (!is_string($id) || !preg_match('/^[a-f0-9]{16}$/', $id)) {
                throw new InvalidArgumentException(sprintf('O id do card %d é inválido.', $index + 1));
            }
            if (isset($ids[$id])) {
                throw new InvalidArgumentException('Os ids dos cards devem ser únicos.');
            }
            $ids[$id] = true;

            $title = self::cardString($rawCard, 'title', 160, '');
            $description = self::cardString($rawCard, 'description', 1000, '');
            $url = self::cardString($rawCard, 'url', 2048, '');
            $image = self::cardString($rawCard, 'image', 2048, '');
            $layout = self::cardString($rawCard, 'layout', 20, 'compact');
            $cta = self::cardString($rawCard, 'cta', 80, 'Acessar');
            $badge = self::cardString($rawCard, 'badge', 40, '');
            $icon = self::cardString($rawCard, 'icon', 20, 'link');
            if (!in_array($layout, ['compact', 'cover', 'button', 'mini', 'banner'], true)) {
                throw new InvalidArgumentException(sprintf('O formato visual do card %d é inválido.', $index + 1));
            }
            if (!in_array($icon, self::ICONS, true)) {
                throw new InvalidArgumentException(sprintf('O ícone do card %d é inválido.', $index + 1));
            }

            $color = self::cardString($rawCard, 'color', 7, '#147d69');
            $textColor = self::cardString($rawCard, 'textColor', 7, '#ffffff');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) {
                throw new InvalidArgumentException(sprintf('As cores do card %d devem estar em hexadecimal.', $index + 1));
            }
            $color = strtolower($color);
            $textColor = strtolower($textColor);

            $enabled = self::boolField($rawCard, 'enabled', false, $index);
            $newTab   = self::boolField($rawCard, 'newTab',  false, $index);
            $highlight = self::boolField($rawCard, 'highlight', false, $index);
            $showArrow = self::boolField($rawCard, 'showArrow', true, $index);

            // Per-card typography
            $titleWeight = self::cardString($rawCard, 'titleWeight', 10, '');
            $validWeights = ['300', '400', '500', '600', '700', '800', '900', ''];
            if (!in_array($titleWeight, $validWeights, true)) {
                $titleWeight = '';
            }
            $titleFontSize = self::cardString($rawCard, 'titleFontSize', 6, '');
            $validSizes = ['xs', 'sm', 'md', 'lg', 'xl', ''];
            if (!in_array($titleFontSize, $validSizes, true)) {
                $titleFontSize = '';
            }
            $titleColor = self::cardString($rawCard, 'titleColor', 7, '');
            if ($titleColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $titleColor)) {
                $titleColor = '';
            }
            $titleColor = $titleColor !== '' ? strtolower($titleColor) : '';
            $descColor = self::cardString($rawCard, 'descColor', 7, '');
            if ($descColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $descColor)) {
                $descColor = '';
            }
            $descColor = $descColor !== '' ? strtolower($descColor) : '';

            // Per-card size and alignment
            $cardItemSize = self::cardString($rawCard, 'cardItemSize', 4, '');
            if (!in_array($cardItemSize, ['xs', 'sm', 'md', 'lg', 'xl', ''], true)) {
                $cardItemSize = '';
            }
            $cardItemAlign = self::cardString($rawCard, 'cardItemAlign', 8, '');
            if (!in_array($cardItemAlign, ['left', 'center', 'right', ''], true)) {
                $cardItemAlign = '';
            }

            // Per-card opacity (0–100, stored as integer string)
            $cardOpacity = (int) ($rawCard['cardOpacity'] ?? 100);
            if ($cardOpacity < 0) {
                $cardOpacity = 0;
            }
            if ($cardOpacity > 100) {
                $cardOpacity = 100;
            }

            // Maps embed src — aceita qualquer input não vazio
            $embedSrc = '';
            if ($type === 'maps') {
                $raw = self::cardString($rawCard, 'embedSrc', 2048, '');
                $embedSrc = $raw; // conversão acontece no PublicView::mapsEmbedUrl()
            }

            // Photo size (only for type=photo)
            $photoSize = self::cardString($rawCard, 'photoSize', 10, 'md');
            if (!in_array($photoSize, ['xs', 'sm', 'md', 'lg', 'xl', 'full'], true)) {
                $photoSize = 'md';
            }

            if ($url !== '' && !self::isAllowedUrl($url, false)) {
                throw new InvalidArgumentException(sprintf('A URL do card %d é inválida.', $index + 1));
            }
            if ($image !== '' && !self::isHttpOrMediaUrl($image)) {
                throw new InvalidArgumentException(sprintf('A imagem do card %d é inválida.', $index + 1));
            }
            if ($enabled && $type === 'link' && $url === '') {
                throw new InvalidArgumentException(sprintf('O card %d precisa de uma URL para ser ativado.', $index + 1));
            }
            if ($enabled && $type === 'image' && $url === '') {
                throw new InvalidArgumentException(sprintf('O card de imagem %d precisa de uma URL para ser ativado.', $index + 1));
            }
            if ($enabled && $type === 'maps' && $url === '') {
                throw new InvalidArgumentException(sprintf('O card de mapa %d precisa de uma URL do Google Maps para ser ativado.', $index + 1));
            }

            $cards[] = [
                'id'            => $id,
                'type'          => $type,
                'title'         => $title,
                'description'   => $description,
                'url'           => $url,
                'image'         => $image,
                'layout'        => $layout,
                'cta'           => $cta !== '' ? $cta : 'Acessar',
                'icon'          => $icon,
                'color'         => $color,
                'textColor'     => $textColor,
                'titleWeight'   => $titleWeight,
                'titleFontSize' => $titleFontSize,
                'titleColor'    => $titleColor,
                'descColor'     => $descColor,
                'cardItemSize'  => $cardItemSize,
                'cardItemAlign' => $cardItemAlign,
                'cardOpacity'   => $cardOpacity,
                'embedSrc'      => $embedSrc,
                'photoSize'     => $photoSize,
                'enabled'       => $enabled,
                'newTab'        => $newTab,
                'highlight'     => $highlight,
                'showArrow'     => $showArrow,
                'badge'         => $badge,
            ];
        }
        return $cards;
    }

    /** @param array<string,mixed> $card */
    private static function cardString(array $card, string $field, int $maxLength, string $default): string
    {
        if (!array_key_exists($field, $card) || $card[$field] === null) {
            return $default;
        }
        if (!is_string($card[$field])) {
            throw new InvalidArgumentException(sprintf('O campo %s do card deve ser texto.', $field));
        }
        $value = trim($card[$field]);
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidArgumentException(sprintf('O campo %s do card excede o limite.', $field));
        }
        return $value;
    }

    /** @param array<string,mixed> $card */
    private static function boolField(array $card, string $field, bool $default, int $index): bool
    {
        if (!array_key_exists($field, $card)) {
            return $default;
        }
        if (is_bool($card[$field])) {
            return $card[$field];
        }
        if (is_int($card[$field]) && ($card[$field] === 0 || $card[$field] === 1)) {
            return $card[$field] === 1;
        }
        throw new InvalidArgumentException(sprintf('O campo %s do card %d deve ser booleano.', $field, $index + 1));
    }

    /** @param mixed $value */
    private static function socials(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('As redes sociais devem ser uma lista.');
        }

        $socials = [];
        $platforms = [];
        foreach ($value as $index => $rawSocial) {
            if (!is_array($rawSocial)) {
                throw new InvalidArgumentException(sprintf('A rede social %d é inválida.', $index + 1));
            }
            $platform = $rawSocial['platform'] ?? null;
            $url = $rawSocial['url'] ?? null;
            if (!is_string($platform) || !in_array($platform, self::SOCIAL_PLATFORMS, true)) {
                throw new InvalidArgumentException(sprintf('A plataforma social %d é inválida.', $index + 1));
            }
            if (isset($platforms[$platform])) {
                throw new InvalidArgumentException('Não é possível repetir uma plataforma social.');
            }
            if (!is_string($url) || trim($url) === '' || mb_strlen(trim($url), 'UTF-8') > 2048) {
                throw new InvalidArgumentException(sprintf('A URL social %d é inválida.', $index + 1));
            }
            $url = trim($url);
            if (!self::isAllowedUrl($url, false)) {
                throw new InvalidArgumentException(sprintf('A URL social %d é inválida.', $index + 1));
            }
            $platforms[$platform] = true;
            $socials[] = ['platform' => $platform, 'url' => $url];
        }
        return $socials;
    }

    private static function isAllowedUrl(string $url, bool $allowMediaPath): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        if ($allowMediaPath && preg_match('#^/media/[a-f0-9]{16}$#', $url)) {
            return true;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (in_array($scheme, ['http', 'https'], true)) {
            return isset($parts['host']) && $parts['host'] !== '';
        }
        $target = $parts['path'] ?? '';
        return is_string($target) && $target !== '';
    }

    private static function isHttpOrMediaUrl(string $url): bool
    {
        if (preg_match('#^/media/[a-f0-9]{16}$#', $url)) {
            return true;
        }
        if (!self::isAllowedUrl($url, false)) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true);
    }
}
