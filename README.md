# Nexus Commerce ⚡

Marketplace multi-vendedor com catálogo, sellers moderados, checkout, pagamentos, custódia financeira (escrow), disputas, logística e painel operacional.

> Status: base avançada de desenvolvimento/pré-produção. Antes de operar com dinheiro real, configure as credenciais, execute as migrations e valide os testes do backend.

## Stack

- **Backend:** Laravel 11 / PHP 8.2+
- **Frontend:** Vue 3 / Vite / Pinia / Vue Router / Tailwind CSS
- **Banco:** PostgreSQL
- **Infraestrutura:** Docker, Redis, filas, Railway e Nginx
- **Pagamentos:** Asaas (PIX/cartão) com webhook protegido e idempotente
- **E-mail/OTP:** Brevo/Resend conforme configuração do ambiente

## Estrutura

```text
backend/   API Laravel, domínio, migrations, policies, filas e testes
frontend/  SPA Vue, views, componentes, stores e router
```

## Funcionalidades

- Cadastro e login com OTP.
- Perfis `ADMIN`, `SELLER` e `CUSTOMER`.
- Aprovação de sellers antes de operar.
- Catálogo público, categorias e estoque concorrente.
- Carrinho, frete e checkout protegido.
- PIX/cartão via Asaas.
- Webhook Asaas com token e idempotência persistente.
- Escrow, ledger, liberação após entrega e disputas.
- Rastreamento e transições logísticas controladas.
- Policies para autorização de pedidos e operações protegidas.
- Sessão por cookie `HttpOnly`, `Secure` e `SameSite`.

## Configuração local

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Configure no `.env`:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SESSION_DRIVER=database
SESSION_HTTP_ONLY=true
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
CORS_ALLOWED_ORIGINS=http://localhost:5173
ASAAS_API_KEY=
ASAAS_BASE_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_TOKEN=
BREVO_API_KEY=
```

### Frontend

```bash
cd frontend
npm install
copy .env.example .env
npm run dev
```

Configure `frontend/.env`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

Em PowerShell, o comando equivalente para copiar o arquivo é:

```powershell
Copy-Item .env.example .env
```

## Produção

Frontend:

- https://frontend-production-3603.up.railway.app

API:

- https://pleasant-light-production.up.railway.app

Para produção, configure:

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
CORS_ALLOWED_ORIGINS=https://frontend-production-3603.up.railway.app
```

Nunca versione `.env`, chaves Asaas, chaves de e-mail ou credenciais administrativas.

## Administrador inicial

Provisione o administrador somente por ambiente seguro:

```bash
php artisan tinker --execute="App\\Models\\User::firstOrCreate(['email' => env('ADMIN_DEFAULT_EMAIL', 'admin@seudominio.com')], ['name' => 'Nexus Master', 'password' => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'sua-senha-segura')), 'role' => 'ADMIN', 'is_approved' => true]);"
```

Defina `ADMIN_DEFAULT_EMAIL` e `ADMIN_DEFAULT_PASSWORD` no ambiente. Não use credenciais padrão em produção.

## Testes e validação

Frontend:

```bash
cd frontend
npm run build
```

Backend:

```bash
cd backend
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test
```

## Segurança

- Autenticação principal por cookie `HttpOnly`; o frontend não persiste token de sessão.
- Cookies cross-origin exigem HTTPS, `SameSite=None` e CORS com credenciais em produção.
- Não registre PAN, CVV, tokens, OTP ou payloads sensíveis.
- Use tokenização oficial do provedor de pagamentos para cartões.
- Webhooks devem receber `ASAAS_WEBHOOK_TOKEN` e permanecer idempotentes.
- Revise migrations e execute backup antes de migrar banco de produção.
