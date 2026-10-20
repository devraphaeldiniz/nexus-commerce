# Contribuindo

## Fluxo

1. Crie uma branch descritiva a partir de `main`.
2. Faça alterações pequenas e focadas.
3. Execute o build do frontend e os testes do backend.
4. Atualize a documentação quando alterar contratos de API.
5. Abra um Pull Request explicando contexto, impacto e validação.

## Padrões

- PHP segue PSR-12 e Laravel Pint.
- Vue usa Composition API e `<script setup>`.
- Segredos ficam apenas em variáveis de ambiente.
- Rotas protegidas devem possuir autenticação e autorização no backend.
- Alterações financeiras precisam de idempotência e testes.

## Checklist

- [ ] `npm run build`
- [ ] `php artisan test`
- [ ] migrations revisadas
- [ ] documentação atualizada
- [ ] nenhum segredo adicionado
- [ ] impacto de segurança avaliado
