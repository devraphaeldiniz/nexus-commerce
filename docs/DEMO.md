# Roteiro de demonstração

## Fluxo recomendado

1. Abrir o storefront e mostrar busca, filtros e categorias.
2. Abrir um produto e adicionar ao carrinho.
3. Simular cálculo de frete.
4. Mostrar a tela de autenticação com OTP.
5. Mostrar o painel de seller e aprovação operacional.
6. Demonstrar criação/edição de produto e controle de estoque.
7. Mostrar pedidos e transição `SHIPPED → DELIVERED`.
8. Explicar escrow, ledger e webhook idempotente.
9. Abrir GitHub Actions e mostrar frontend/backend verdes.
10. Apresentar `docs/ARCHITECTURE.md` e `docs/openapi.yaml`.

## Mensagem de apresentação

> Nexus Commerce é uma demonstração de marketplace multi-vendedor com foco em domínio financeiro, autorização, operação logística e qualidade de entrega. O backend recalcula valores e estoque, webhooks são autenticados e idempotentes, e o repositório possui CI executando build e testes.

## Checklist antes da demo

- [ ] API acessível
- [ ] Frontend compilado
- [ ] seed executado
- [ ] usuário demo criado
- [ ] nenhuma chave exposta
- [ ] GitHub Actions verde
- [ ] banco sem dados sensíveis
