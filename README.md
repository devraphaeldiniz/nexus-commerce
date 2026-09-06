# Nexus Commerce

Plataforma full-stack de marketplace e e-commerce desenvolvida com backend robusto em Laravel e SPA reativo em Vue.js, conteinerizada e implantada em produção na plataforma Railway.

---

## Deploy em Produção

* **Frontend:** https://frontend-production-3603.up.railway.app
* **API Backend:** https://pleasant-light-production.up.railway.app

---

## Tecnologias

### Backend
* **PHP 8.4** com Nginx e PHP-FPM (`serversideup/php:8.4-fpm-nginx`)
* **Laravel 11**
* **PostgreSQL** para persistência relacional
* **Composer** para gestão de pacotes

### Frontend
* **Vue.js** com Vite
* **Lucide Icons**
* **Nginx Alpine** para servir os assets estáticos com suporte a SPA routing

### Infraestrutura & DevOps
* **Docker** (Multi-stage build para frontend e runtime otimizado para PHP)
* **Railway** (PaaS para orquestração de containers e provisionamento do PostgreSQL)

---

## Estrutura do Projeto

```text
nexus-commerce/
├── backend/                # Aplicação Laravel (API)
│   ├── app/
│   ├── config/
│   ├── database/migrations/
│   ├── routes/
│   └── Dockerfile          # Configuração Docker do serviço PHP/Nginx
├── frontend/               # Aplicação Vue / Vite
│   ├── src/
│   ├── package.json
│   └── Dockerfile          # Configuração Docker da SPA
├── Dockerfile              # Dockerfile raiz de produção (Frontend)
├── docker-compose.yml      # Orquestração para ambiente local
└── railway.toml            # Declaração do builder Railway
Funcionalidades Principais
Arquitetura de Marketplace: Ledger de transações, custódia (escrow) e requisições de payout.

Governança & Perfis: Gestão de usuários, papéis (roles), auditoria KYC e logs de segurança.

Logística & Pedidos: Rastreamento, cotação de frete e gestão de disputas/reivindicações.

Autenticação: Tokens de API seguros com controle de expiração.

Executando Localmente
Pré-requisitos
Docker e Docker Compose instalados

Git

Passos
Clone o repositório:

Bash
git clone [https://github.com/devraphaeldiniz/nexus-commerce.git](https://github.com/devraphaeldiniz/nexus-commerce.git)
cd nexus-commerce
Suba o ambiente via Docker Compose:

Bash
docker compose up -d --build
Instale as dependências e rode as migrações no container do backend:

Bash
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
Acesse:

Frontend: http://localhost:5173 ou porta mapeada do container

API: http://localhost:8000

Licença
Este projeto é desenvolvido para fins de demonstração técnica e comercial.
