# Nexus Commerce ⚡

Plataforma de marketplace completa e escalável, com fluxo integrado de custódia financeira (Escrow), moderação de lojistas em tempo real, painel operacional unificado e controle de autenticação via RBAC.

---

## 🛠️ Stack Tecnológica

* **Backend:** Laravel 11 / PHP 8.3
* **Frontend:** Vue.js 3 / Vite / Tailwind CSS
* **Banco de Dados:** PostgreSQL
* **Infraestrutura / Cloud:** Railway (Containers Docker independentes)

---

## 🚀 Arquitetura e Funcionalidades

### 1. Autenticação e Perfis (Roles)
* **ADMIN:** Acesso global irrestrito, moderação e aprovação de lojistas parceiros, publicação de produtos no catálogo, gestão de perfil, auditoria de segurança e alteração de credenciais.
* **SELLER:** Fluxo de cadastro com moderação prévia obrigatória (`is_approved = false`), dashboard de métricas e controle de catálogo de vendas.
* **CUSTOMER:** Navegação pública, busca em catálogo, cálculo de frete, carrinho e fluxo de checkout integrado.

### 2. Painel Unificado de Operações
* **🛡️ Moderação de Lojistas:** Fila em tempo real para auditoria e aprovação de novos parceiros comerciais.
* **📦 Anúncio de Produtos:** Cadastro ágil de itens com controle de estoque, categorias e precificação.
* **👤 Perfil & Documento:** Gestão de dados cadastrais com vínculo e validação de CPF.
* **🔒 Gestão de Credenciais:** Alteração de senha segura com validação de hash prévio e auditoria.

---

## 🌐 Ambientes em Produção

* **Frontend:** [https://frontend-production-3603.up.railway.app](https://frontend-production-3603.up.railway.app)
* **API Backend:** [https://pleasant-light-production.up.railway.app](https://pleasant-light-production.up.railway.app)

---

## 🔐 Configuração Inicial do Administrador

Para provisionar o primeiro usuário administrativo em ambientes locais ou isolados, utilize o comando Artisan ou configure as variáveis de ambiente correspondentes:

```bash
php artisan tinker --execute="App\Models\User::firstOrCreate(['email' => env('ADMIN_DEFAULT_EMAIL', 'admin@seudominio.com')], ['name' => 'Nexus Master', 'password' => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'sua-senha-segura')), 'role' => 'ADMIN', 'is_approved' => true]);"
