# Nexus Commerce API

API e domínio de um marketplace multi-vendedor construído com Laravel 11.

## Responsabilidades

- autenticação e OTP;
- catálogo, categorias e estoque;
- checkout e pagamentos Asaas;
- escrow, ledger e payouts;
- disputas e logística;
- políticas de autorização;
- processamento idempotente de webhooks;
- filas e notificações.

## Desenvolvimento

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Qualidade

```bash
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test
```

## Integrações

As chaves devem ser fornecidas exclusivamente por variáveis de ambiente:

```env
ASAAS_API_KEY=
ASAAS_BASE_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_TOKEN=
BREVO_API_KEY=
```

O webhook público é:

```text
POST /api/webhooks/asaas
```

Ele exige `asaas-access-token` e persiste eventos para impedir reprocessamento.

## Segurança

- sessão principal por cookie HttpOnly;
- CORS com credenciais e origens explícitas;
- rate limiting em autenticação e checkout;
- tokens de cartão nunca persistidos como PAN/CVV;
- autorização por Policy e escopo de usuário/vendedor;
- nenhum segredo deve ser commitado.
