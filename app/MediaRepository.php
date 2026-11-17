<?php
declare(strict_types=1);

namespace Bio;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MediaRepository
{
    private const MAX_BYTES = 10485760; // 10 MB

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Store an uploaded PNG, JPEG or WEBP and return its public application path.
     *
     * @param array<string,mixed> $file
     */
    public function store(array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_OK;
        if (!is_int($error) && !(is_string($error) && ctype_digit($error))) {
            throw new InvalidArgumentException('O upload de mídia é inválido.');
        }
        if ((int) $error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber a imagem enviada.');
        }

        $path = $file['tmp_name'] ?? null;
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('O arquivo de imagem não foi encontrado.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 10 MB.');
        }
        if (isset($file['size']) && (is_int($file['size']) || is_float($file['size'])) && (int) $file['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 10 MB.');
        }

        $imageInfo = @getimagesize($path);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (!is_array($imageInfo) || !is_string($mime) || !in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('A mídia deve ser uma imagem PNG, JPEG ou WEBP válida.');
        }
        $reportedMime = $imageInfo['mime'] ?? null;
        if (!is_string($reportedMime) || $reportedMime !== $mime) {
            throw new InvalidArgumentException('O tipo real da imagem não pôde ser confirmado.');
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 10 MB.');
        }

        $id = bin2hex(random_bytes(8));
        $statement = $this->pdo->prepare(
            'INSERT INTO media (id, mime, content, created_at) VALUES (:id, :mime, :content, :created_at)'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->bindValue(':mime', $mime, PDO::PARAM_STR);
        $statement->bindValue(':content', $content, PDO::PARAM_LOB);
        $statement->bindValue(':created_at', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
        $statement->execute();

        return '/media/' . $id;
    }

    /** @return array{mime:string,content:string}|null */
    public function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT mime, content FROM media WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $content = $row['content'] ?? '';
        if (is_resource($content)) {
            $content = stream_get_contents($content);
        }
        return [
            'mime' => (string) ($row['mime'] ?? ''),
            'content' => is_string($content) ? $content : '',
        ];
    }

    /** @return list<string> */
    public static function references(array $page): array
    {
        $urls = [(string) ($page['avatar'] ?? '')];
        foreach (($page['cards'] ?? []) as $card) {
            $urls[] = (string) ($card['image'] ?? '');
        }
        $ids = [];
        foreach ($urls as $url) {
            if (preg_match('~^/media/([a-f0-9]{16})$~', $url, $match)) {
                $ids[$match[1]] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Only collect old media explicitly removed by this mutation. Never sweep
     * newly uploaded, not-yet-associated media from another request.
     * @param list<string> $candidates
     */
    public function pruneUnused(array $candidates): void
    {
        if (!$candidates) {
            return;
        }
        $used = [];
        $rows = $this->pdo->query('SELECT avatar, cards FROM pages');
        while ($page = $rows->fetch(PDO::FETCH_ASSOC)) {
            $page['cards'] = json_decode((string) $page['cards'], true) ?: [];
            foreach (self::references($page) as $id) {
                $used[$id] = true;
            }
        }
        $delete = $this->pdo->prepare('DELETE FROM media WHERE id = :id');
        foreach (array_unique($candidates) as $id) {
            if (preg_match('/^[a-f0-9]{16}$/', $id) && !isset($used[$id])) {
                $delete->execute(['id' => $id]);
            }
        }
    }
}
