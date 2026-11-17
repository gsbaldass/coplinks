# CopLinks — Painel de Links

O projeto reúne 36 páginas de links independentes em um painel administrativo, com PHP 8.3 orientado a objetos, HTML e CSS. Não utiliza frameworks, Composer ou Node; um pequeno script JavaScript puro é usado apenas para enviar campos ainda não salvos à prévia. O PHP continua validando, autenticando, renderizando e persistindo a aplicação.

As 36 páginas iniciais são rascunhos: `Inclusão` e `Página 02` a `Página 36`. Os nomes, fotos e links definitivos ainda não foram fornecidos. Não há links fictícios publicados. Você pode criar páginas adicionais; 36 não é um limite de uso.

## Rodar no Laragon

### Requisitos

- **Laragon** com PHP 8.3+ e MySQL
- Extensões PHP habilitadas em `php.ini`: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`
- Para testes locais: `pdo_sqlite`, `dom`

### Configuração rápida

1. **Configurar variáveis de ambiente**

   No VirtualHost do Laragon, adicione dentro do bloco `<VirtualHost>`:

   ```apache
   SetEnv DATABASE_URL "mysql://root:@localhost:3306/coplinks"
   SetEnv PUBLIC_ORIGIN "http://localhost"
   SetEnv ADMIN_EMAILS "convencao.oestepaulista.links@gmail.com"
   SetEnv ADMIN_PASSWORD "sua-senha-aqui"
   ```

   Ou crie um arquivo `.env` na raiz do projeto e use `vlucas/phpdotenv` para carregá-lo (adicione ao `bootstrap.php`). **Não coloque segredos no Git.**

2. **Criar o banco e executar migrations**

   Crie o banco `coplinks` no MySQL (via Laragon / phpMyAdmin):

   ```sql
   CREATE DATABASE coplinks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Depois rode a migração para criar as tabelas e semear as 36 páginas:

   ```bash
   php bin/migrate.php
   ```

3. **Configurar o VirtualHost**

   O `DocumentRoot` deve apontar para a pasta `public/` do projeto, não para a raiz. No Laragon, o caminho seria algo como:

   ```apache
   DocumentRoot "C:/Users/guilh/OneDrive/Documents/coplinks/public"
   <Directory "C:/Users/guilh/OneDrive/Documents/coplinks/public">
       AllowOverride All
       Options -Indexes
       Require all granted
   </Directory>
   ```

   O `mod_rewrite` já vem habilitado no Laragon. O `.htaccess` em `public/` cuida de rotear tudo para `index.php`.

4. **Gerar a senha de admin (produção)**

   Para produção, use um hash bcrypt em vez de texto plano:

   ```bash
   php -r "echo password_hash('sua-senha', PASSWORD_BCRYPT);"
   ```

   E coloque o resultado em `ADMIN_PASSWORD`.

5. **Acessar o painel**

   Abra `http://localhost/login`, informe a senha configurada em `ADMIN_PASSWORD` e entre.

### Servidor embutido (alternativa rápida)

```bash
DATABASE_URL=mysql://root:@localhost:3306/coplinks \
PUBLIC_ORIGIN=http://localhost \
ADMIN_PASSWORD=cop010108 \
php -d upload_max_filesize=2M -d post_max_size=4M \
  -S 0.0.0.0:8000 -t public router.php
```

Acesse em `http://localhost:8000`.

## Usar o painel

Acesse `/login`, informe a senha de administrador e entre. A página pública (`/{slug}`) não exige login.

Em **Minhas páginas**, use busca, filtros de estado e grade/lista. Clique em **Personalizar**, defina o nome e o endereço, adicione cards e salve. Publicação e desativação são controladas por botões separados.

A aba **Links e cards** oferece link, imagem, título de seção e bloco de texto. Cada card pode ter título, descrição, miniatura e destino. Links com imagem suportam três layouts: **Cartão compacto**, **Imagem em destaque** e **Imagem + botão Acessar**. Cards podem ser ocultados, duplicados, excluídos e reordenados.

Em **Perfil da página**, edite nome, título, descrição, foto e identificador da URL. Em **Aparência**, aplique temas e personalize cores, tipografia e cantos. Em **Redes sociais**, configure os ícones que aparecem na página pública.

A prévia lateral é renderizada pelo PHP e atualizada automaticamente enquanto você edita. Alterações não salvas não vão ao banco.

**Estatísticas** mostra carregamentos de páginas e cliques em cards. **Configurações** permite exportar páginas em JSON.

## Estrutura PHP POO

`app/App.php` é o controlador de rotas. `PageRepository` gerencia páginas e estatísticas. `PageValidator` normaliza campos e rejeita URLs inseguras. `Database` centraliza PDO, migrations e seed idempotente. `MediaRepository` valida e armazena imagens no banco como BLOB. `Auth` implementa autenticação com sessão PHP nativa e CSRF. `AdminView` e `PublicView` renderizam as interfaces.

`public/` é a única raiz acessível pelo servidor web. `bootstrap.php` contém autoload e helpers. `bin/` guarda scripts de migração. `tests/regression.php` usa SQLite temporário para testes isolados.

## Autenticação

O sistema usa **sessão PHP nativa** com cookie `HttpOnly`. A senha é verificada contra a variável `ADMIN_PASSWORD`, que aceita:
- **Texto plano** — para desenvolvimento local
- **Hash bcrypt/argon2** — gerado por `password_hash()` — recomendado em produção

Formulários são protegidos por token CSRF armazenado na sessão.

## Banco de dados

`DATABASE_URL` deve usar o formato `mysql://usuario:senha@host:porta/banco`. Se não estiver definida, o fallback cria `var/bio.sqlite` (apenas para testes locais). As imagens ficam em BLOBs no banco.

## Verificações

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/regression.php
```

Os testes abrangem migrations e seed idempotente, CRUD, duplicação, busca, links inválidos, endereços reservados, contagem de visitas/cliques, autorização, CSRF e escape de HTML.
