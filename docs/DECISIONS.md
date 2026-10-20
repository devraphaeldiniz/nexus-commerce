# Decisões técnicas

## Sessão por cookie HttpOnly

Escolhida para evitar exposição do token de sessão a JavaScript e reduzir impacto de XSS. O frontend envia credenciais via Axios e o backend valida o cookie `nexus_auth`.

## PostgreSQL

Escolhido para suportar integridade referencial, UUIDs, concorrência de estoque, consultas analíticas e evolução do ledger financeiro.

## Idempotência de pagamentos

Eventos do Asaas são persistidos em `payment_webhook_events` com chave única. Retries do gateway não devem duplicar atualização de pedido, escrow ou ledger.

## Valores monetários em centavos

Preços e totais são armazenados como inteiros em centavos para evitar erros de ponto flutuante.

## Backend como autoridade

Preço, estoque, permissões, status de pedido e comissões são recalculados no backend. Valores enviados pelo navegador são tratados como sugestão e nunca como verdade.

## CI obrigatório

Pull requests e pushes para `main` executam build do frontend e testes Laravel em PostgreSQL. Falhas de qualidade devem bloquear a evolução da branch principal.
