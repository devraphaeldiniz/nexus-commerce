# Nexus Commerce Web

SPA do marketplace Nexus Commerce, construída com Vue 3, Vite, Pinia e Vue Router.

## Scripts

```bash
npm install
npm run dev
npm run build
npm run preview
```

## Configuração

Crie `.env` a partir de `.env.example`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

Em produção, use a URL pública da API. O Axios utiliza `withCredentials` para enviar o cookie HttpOnly de sessão.

## Rotas principais

- `/` — storefront e catálogo;
- `/products/:id` — detalhe do produto;
- `/login` — autenticação;
- `/profile` — perfil e segurança;
- `/orders` — pedidos do cliente;
- `/seller` — operação do vendedor aprovado.

## Arquitetura

```text
src/views        telas e fluxos de navegação
src/components   componentes reutilizáveis
src/stores       estado global de autenticação e carrinho
src/router       navegação e guards de acesso
src/assets       imagens e recursos visuais
```

O token de autenticação não é salvo no `localStorage`. A sessão é mantida pelo cookie HttpOnly emitido pela API.
