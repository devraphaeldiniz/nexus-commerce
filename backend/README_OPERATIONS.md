# Operação e validação (fase 4)

## Migrações e worker

- `php artisan migrate --force` antes de iniciar a aplicação.
- Worker: `php artisan queue:work --sleep=3 --tries=3 --timeout=90`.
- Não execute migrações concorrentes em múltiplos containers.

## Backup

Faça backup diário do PostgreSQL com `pg_dump` e teste restauração periodicamente. Armazene fora do host e proteja as credenciais; backups devem ser criptografados.

## Produção

Use `docker compose -f docker-compose.prod.yml --env-file .env.production up -d`. Nunca comite `.env.production`; configure `APP_KEY`, banco, Redis, Asaas e token do webhook via secret manager.

## Validação

Execute `php artisan test` e `composer audit --locked`. O webhook Asaas exige token e reconcilia o pagamento via API antes de alterar o pedido. Falha na reconciliação retorna `503` e não libera o pedido.
