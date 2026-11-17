<?php

declare(strict_types=1);

namespace Bio;

use RuntimeException;

/**
 * Autenticação da aplicação via sessão PHP nativa com senha configurável.
 *
 * Não depende de nenhum serviço externo. A identidade do administrador é
 * determinada pela variável de ambiente ADMIN_PASSWORD (hash bcrypt ou senha
 * em texto plano para desenvolvimento local).
 */
final class Auth
{
    private const SESSION_KEY  = 'bio_admin_user';
    private const CSRF_KEY     = 'bio_csrf_token';
    private const AUTHORIZED_EMAIL = 'convencao.oestepaulista.links@gmail.com';

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Retorna o usuário autenticado ou null se não há sessão ativa.
     *
     * @return array{openId:string,name:string,email:string}|null
     */
    public static function user(): ?array
    {
        self::startSession();
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user)) {
            return null;
        }
        return $user;
    }

    /**
     * Exige usuário autenticado — lança exceção 403 se não houver sessão.
     *
     * @return array{openId:string,name:string,email:string}
     */
    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            throw new RuntimeException('Acesso não autorizado.', 403);
        }
        return $user;
    }

    /**
     * Se não há usuário ativo, redireciona para o formulário de login em vez
     * de lançar. Retorna o usuário quando já autenticado.
     *
     * @return array{openId:string,name:string,email:string}
     */
    public static function denied(): bool
    {
        return self::user() === null;
    }

    /**
     * Exibe o formulário de login (GET /auth/login).
     */
    public static function beginLogin(): never
    {
        self::startSession();
        // Se já autenticado, vai direto ao painel.
        if (isset($_SESSION[self::SESSION_KEY])) {
            self::redirect('/admin');
        }
        self::redirect('/login');
    }

    /**
     * Processa o envio do formulário de login (POST /auth/callback).
     */
    public static function callback(): never
    {
        self::startSession();

        $password = trim((string) ($_POST['password'] ?? ''));
        if ($password === '') {
            self::redirect('/login?error=empty');
        }

        if (!self::checkPassword($password)) {
            self::redirect('/login?error=invalid');
        }

        // Regenera o ID de sessão para prevenir fixação de sessão.
        session_regenerate_id(true);

        $email = self::adminEmail();
        $_SESSION[self::SESSION_KEY] = [
            'openId' => 'local-admin',
            'name'   => 'Administrador',
            'email'  => $email,
        ];
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));

        self::redirect('/admin');
    }

    /**
     * Encerra a sessão e redireciona para a raiz.
     */
    public static function logout(): never
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
        self::redirect('/');
    }

    /**
     * Retorna o token CSRF da sessão atual (vazio se não autenticado).
     */
    public static function csrf(): string
    {
        self::startSession();
        if (!isset($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::CSRF_KEY];
    }

    /**
     * Verifica o token CSRF em tempo constante.
     */
    public static function verifyCsrf(string $provided): bool
    {
        if ($provided === '' || strlen($provided) > 256) {
            return false;
        }
        $expected = self::csrf();
        if ($expected === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure   = self::isHttps();
            $sameSite = $secure ? 'Strict' : 'Lax';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);
            session_start();
        }
    }

    /**
     * Verifica a senha enviada contra ADMIN_PASSWORD.
     *
     * Aceita:
     *   - Hash bcrypt/argon gerado por password_hash()
     *   - Senha em texto plano (apenas para desenvolvimento local)
     */
    private static function checkPassword(string $input): bool
    {
        $stored = trim((string) (getenv('ADMIN_PASSWORD') ?: ''));
        if ($stored === '') {
            return false;
        }

        // Hash bcrypt/argon2 começa com '$'
        if (str_starts_with($stored, '$')) {
            return password_verify($input, $stored);
        }

        // Texto plano — comparação em tempo constante.
        return hash_equals($stored, $input);
    }

    private static function adminEmail(): string
    {
        $configured = trim((string) (getenv('ADMIN_EMAILS') ?: ''));
        if ($configured !== '') {
            $parts = preg_split('/[,;\s]+/', $configured, 2, PREG_SPLIT_NO_EMPTY);
            if ($parts !== false && isset($parts[0])) {
                return strtolower(trim($parts[0]));
            }
        }
        return self::AUTHORIZED_EMAIL;
    }

    private static function isHttps(): bool
    {
        $scheme = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($scheme !== '' && $scheme !== 'off') {
            return true;
        }
        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }

    private static function redirect(string $location): never
    {
        header('Cache-Control: no-store');
        header('Location: ' . $location, true, 302);
        exit;
    }
}
