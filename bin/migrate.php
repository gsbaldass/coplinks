#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Bio\Database;
use Bio\PageRepository;

try {
    Database::migrate();
    $stats = (new PageRepository())->stats();
    printf(
        "Migração concluída. Páginas: %d (publicadas: %d, rascunhos: %d). Visitas: %d. Cliques: %d.\n",
        $stats['total'],
        $stats['published'],
        $stats['draft'],
        $stats['visits'],
        $stats['clicks']
    );
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Falha na migração do banco de dados.\n");
    exit(1);
}
