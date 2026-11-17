<?php

declare(strict_types=1);

namespace Bio;

final class Config
{
    /**
     * Retorna a origem pública configurada (ex.: http://localhost ou https://seu-dominio.com.br).
     * Aceita apenas origens com esquema http (localhost) ou https.
     */
    public static function publicOrigin(): string
    {
        $value = trim((string) (getenv('PUBLIC_ORIGIN') ?: ''));
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if (
            !is_array($parts)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/[\x00-\x20]/', $value)
        ) {
            return '';
        }

        $scheme = $parts['scheme'] ?? '';
        $host   = strtolower((string) $parts['host']);

        // Permite http apenas para localhost em desenvolvimento local.
        if ($scheme !== 'https' && !($scheme === 'http' && $host === 'localhost')) {
            return '';
        }

        if (($parts['path'] ?? '') !== '' && $parts['path'] !== '/') {
            return '';
        }

        return rtrim($value, '/');
    }
}
