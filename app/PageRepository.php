<?php
declare(strict_types=1);

namespace Bio;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class PageRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return list<array<string,mixed>> */
    public function all(string $search = '', string $status = 'all'): array
    {
        if ($status !== 'all' && !in_array($status, ['draft', 'published', 'inactive'], true)) {
            throw new InvalidArgumentException('O filtro de status é inválido.');
        }

        $where = [];
        $parameters = [];
        if (trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(slug LIKE :search_slug OR name LIKE :search_name OR title LIKE :search_title OR description LIKE :search_description)';
            $parameters['search_slug'] = $term;
            $parameters['search_name'] = $term;
            $parameters['search_title'] = $term;
            $parameters['search_description'] = $term;
        }
        if ($status !== 'all') {
            $where[] = 'status = :status';
            $parameters['status'] = $status;
        }

        $sql = 'SELECT id, slug, name, title, description, avatar, status, theme, cards, socials, created_at, updated_at FROM pages';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY updated_at DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $pages = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $pages[] = $this->rowToPage($row);
        }
        return $pages;
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, slug, name, title, description, avatar, status, theme, cards, socials, created_at, updated_at ' .
            'FROM pages WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->rowToPage($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function bySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, slug, name, title, description, avatar, status, theme, cards, socials, created_at, updated_at ' .
            'FROM pages WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => strtolower(trim($slug))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->rowToPage($row) : null;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(array $data): array
    {
        $normalized = PageValidator::validate($data);
        $id = bin2hex(random_bytes(8));
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO pages ' .
            '(id, slug, name, title, description, avatar, status, theme, cards, socials, created_at, updated_at) ' .
            'VALUES (:id, :slug, :name, :title, :description, :avatar, :status, :theme, :cards, :socials, :created_at, :updated_at)'
        );

        try {
            $statement->execute([
                'id' => $id,
                'slug' => $normalized['slug'],
                'name' => $normalized['name'],
                'title' => $normalized['title'],
                'description' => $normalized['description'],
                'avatar' => $normalized['avatar'],
                'status' => $normalized['status'],
                'theme' => $this->encode($normalized['theme']),
                'cards' => $this->encode($normalized['cards']),
                'socials' => $this->encode($normalized['socials']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($this->isIntegrityError($exception)) {
                throw new InvalidArgumentException('Este slug já está em uso.', 0, $exception);
            }
            throw $exception;
        }

        $created = $this->find($id);
        if ($created === null) {
            throw new RuntimeException('Não foi possível ler a página criada.');
        }
        return $created;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function update(string $id, array $data): array
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new RuntimeException('Página não encontrada.');
        }

        $merged = [
            'slug' => $existing['slug'],
            'name' => $existing['name'],
            'title' => $existing['title'],
            'description' => $existing['description'],
            'avatar' => $existing['avatar'],
            'status' => $existing['status'],
            'theme' => $existing['theme'],
            'cards' => $existing['cards'],
            'socials' => $existing['socials'],
        ];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }
        if (array_key_exists('theme', $data) && is_array($data['theme']) && is_array($existing['theme'])) {
            $merged['theme'] = array_replace($existing['theme'], $data['theme']);
        }

        $normalized = PageValidator::validate($merged);
        $statement = $this->pdo->prepare(
            'UPDATE pages SET slug = :slug, name = :name, title = :title, description = :description, ' .
            'avatar = :avatar, status = :status, theme = :theme, cards = :cards, socials = :socials, ' .
            'updated_at = :updated_at WHERE id = :id'
        );

        try {
            $statement->execute([
                'id' => $id,
                'slug' => $normalized['slug'],
                'name' => $normalized['name'],
                'title' => $normalized['title'],
                'description' => $normalized['description'],
                'avatar' => $normalized['avatar'],
                'status' => $normalized['status'],
                'theme' => $this->encode($normalized['theme']),
                'cards' => $this->encode($normalized['cards']),
                'socials' => $this->encode($normalized['socials']),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            if ($this->isIntegrityError($exception)) {
                throw new InvalidArgumentException('Este slug já está em uso.', 0, $exception);
            }
            throw $exception;
        }

        $updated = $this->find($id);
        if ($updated === null) {
            throw new RuntimeException('Não foi possível ler a página atualizada.');
        }
        $unused = array_values(array_diff(MediaRepository::references($existing), MediaRepository::references($updated)));
        (new MediaRepository($this->pdo))->pruneUnused($unused);
        return $updated;
    }

    /** @return array<string,mixed> */
    public function duplicate(string $id): array
    {
        $source = $this->find($id);
        if ($source === null) {
            throw new RuntimeException('Página não encontrada.');
        }

        $baseSlug = (string) $source['slug'];
        $slug = $this->uniqueDuplicateSlug($baseSlug);
        $name = $this->withCopySuffix((string) $source['name'], 120);
        $title = $this->withCopySuffix((string) $source['title'], 160);
        $cards = [];
        foreach ((array) $source['cards'] as $card) {
            if (is_array($card)) {
                $card['id'] = bin2hex(random_bytes(8));
                $cards[] = $card;
            }
        }

        $payload = [
            'slug' => $slug,
            'name' => $name,
            'title' => $title,
            'description' => (string) $source['description'],
            'avatar' => (string) $source['avatar'],
            'status' => 'draft',
            'theme' => is_array($source['theme']) ? $source['theme'] : [],
            'cards' => $cards,
            'socials' => is_array($source['socials']) ? $source['socials'] : [],
        ];

        // A concurrent duplicate may claim the first candidate; retry with a new suffix without overwriting it.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                return $this->create($payload);
            } catch (InvalidArgumentException $exception) {
                if ($exception->getMessage() !== 'Este slug já está em uso.') {
                    throw $exception;
                }
                $payload['slug'] = $this->uniqueDuplicateSlug($baseSlug);
            }
        }
        throw new RuntimeException('Não foi possível gerar um slug exclusivo para a cópia.');
    }

    public function delete(string $id): void
    {
        $existing = $this->find($id);
        $oldMedia = $existing ? MediaRepository::references($existing) : [];
        $transactionStarted = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }
            $visits = $this->pdo->prepare('DELETE FROM visits WHERE page_id = :id');
            $visits->execute(['id' => $id]);
            $page = $this->pdo->prepare('DELETE FROM pages WHERE id = :id');
            $page->execute(['id' => $id]);
            if ($transactionStarted) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        (new MediaRepository($this->pdo))->pruneUnused($oldMedia);
    }

    /** @return array{total:int,published:int,draft:int,visits:int,clicks:int} */
    public function stats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn();
        $published = (int) $this->pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();
        $draft = (int) $this->pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'draft'")->fetchColumn();
        $visits = (int) $this->pdo->query("SELECT COUNT(*) FROM visits WHERE card_id = ''")->fetchColumn();
        $clicks = (int) $this->pdo->query("SELECT COUNT(*) FROM visits WHERE card_id <> ''")->fetchColumn();
        return [
            'total' => $total,
            'published' => $published,
            'draft' => $draft,
            'visits' => $visits,
            'clicks' => $clicks,
        ];
    }

    /** @return array{visits:int,clicks:int,cardClicks:array<string,int>} */
    public function pageStats(string $id): array
    {
        $visitsStatement = $this->pdo->prepare("SELECT COUNT(*) FROM visits WHERE page_id = :id AND card_id = ''");
        $visitsStatement->execute(['id' => $id]);
        $clicksStatement = $this->pdo->prepare("SELECT COUNT(*) FROM visits WHERE page_id = :id AND card_id <> ''");
        $clicksStatement->execute(['id' => $id]);
        $cardsStatement = $this->pdo->prepare(
            "SELECT card_id, COUNT(*) AS total FROM visits WHERE page_id = :id AND card_id <> '' GROUP BY card_id"
        );
        $cardsStatement->execute(['id' => $id]);

        $cardClicks = [];
        while ($row = $cardsStatement->fetch(PDO::FETCH_ASSOC)) {
            $cardClicks[(string) $row['card_id']] = (int) $row['total'];
        }
        return [
            'visits' => (int) $visitsStatement->fetchColumn(),
            'clicks' => (int) $clicksStatement->fetchColumn(),
            'cardClicks' => $cardClicks,
        ];
    }

    public function track(string $pageId, ?string $cardId = null): void
    {
        $card = $cardId === null ? '' : trim($cardId);
        if (mb_strlen($card, 'UTF-8') > 64) {
            throw new InvalidArgumentException('O id do card excede o limite.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO visits (page_id, card_id, occurred_at) VALUES (:page_id, :card_id, :occurred_at)'
        );
        $statement->execute([
            'page_id' => $pageId,
            'card_id' => $card,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function export(): array
    {
        return $this->all();
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function rowToPage(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'avatar' => (string) ($row['avatar'] ?? ''),
            'status' => (string) ($row['status'] ?? 'draft'),
            'theme' => $this->decode($row['theme'] ?? null, []),
            'cards' => $this->decode($row['cards'] ?? null, []),
            'socials' => $this->decode($row['socials'] ?? null, []),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @return array<mixed> */
    private function decode(mixed $value, array $fallback): array
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!is_string($value) || $value === '') {
            return $fallback;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $fallback;
        }
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function isIntegrityError(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        return (string) $exception->getCode() === '23000'
            || (is_array($errorInfo) && (string) ($errorInfo[0] ?? '') === '23000');
    }

    private function slugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM pages WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return $statement->fetchColumn() !== false;
    }

    private function uniqueDuplicateSlug(string $original): string
    {
        $base = rtrim($original, '-');
        $copyBase = $this->truncateSlug($base, '-copy');
        $candidate = $copyBase . '-copy';
        if (!$this->slugExists($candidate)) {
            return $candidate;
        }
        for ($number = 2; $number <= 1000; $number++) {
            $suffix = '-copy-' . $number;
            $candidate = $this->truncateSlug($base, $suffix) . $suffix;
            if (!$this->slugExists($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException('Não foi possível gerar um slug exclusivo para a cópia.');
    }

    private function truncateSlug(string $base, string $suffix): string
    {
        $available = 64 - mb_strlen($suffix, 'UTF-8');
        return rtrim(mb_substr($base, 0, max(1, $available), 'UTF-8'), '-');
    }

    private function withCopySuffix(string $value, int $maxLength): string
    {
        $suffix = ' (cópia)';
        if (mb_strlen($value . $suffix, 'UTF-8') <= $maxLength) {
            return $value . $suffix;
        }
        return mb_substr($value, 0, $maxLength - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
    }
}
