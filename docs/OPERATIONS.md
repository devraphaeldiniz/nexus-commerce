# Operação, staging e sandbox

## Pré-requisitos
- PHP 8.4+ e Composer (use `composer install` dentro de `backend`; não é necessário instalar globalmente).
- Node.js 20+ (use `npm ci` dentro de `frontend`).
- PostgreSQL conforme `.env`/Docker Compose.

## Comandos locais
```powershell
cd backend; composer install; php artisan migrate; php artisan test
cd ..\frontend; npm ci; npm run build
```

## Sandbox Asaas
Configure apenas variáveis locais/staging: `ASAAS_BASE_URL=https://sandbox.asaas.com/api/v3`, `ASAAS_API_KEY` e `ASAAS_WEBHOOK_TOKEN`. Nunca commite valores reais. Teste webhook com payload sintético e token de teste. Não use credenciais reais.

## Staging / smoke test
1. Copie `.env.example` para o ambiente de staging e gere uma chave própria (`php artisan key:generate`).
2. Rode migrações com `php artisan migrate --force` após backup; não use seeders de reset.
3. Verifique `GET /api/health`, login, criação de checkout e consulta do pedido.
4. Configure webhook Asaas sandbox para `/api/webhooks/asaas`; valide o header `asaas-access-token`.
5. Execute `composer audit`, `npm audit --omit=dev`, testes PHP e `npm run build`.

Não há deploy automático ou credenciais incluídas neste repositório.
