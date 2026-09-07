# Nexus Commerce ⚡

Plataforma de marketplace completa e escalável, com fluxo integrado de escrow, moderação de lojistas, painel operacional multifuncional e controle de autenticação unificado.

---

## 🛠️ Stack Tecnológica

* **Backend:** Laravel 11 / PHP 8.3
* **Frontend:** Vue.js 3 / Vite / Tailwind CSS
* **Banco de Dados:** PostgreSQL
* **Infraestrutura / Cloud:** Railway (Containers Docker independentes)

---

## 🚀 Arquitetura e Funcionalidades

### 1. Autenticação e Perfis (Roles)
* **ADMIN:** Acesso global irrestrito, moderação/aprovação de lojistas, publicação direta de produtos na vitrine, gerenciamento de perfil com CPF vinculado e troca de senha.
* **SELLER:** Fluxo de cadastro com status pendente (`is_approved = false`), liberação após moderação administrativa, dashboard de métricas, gestão e cadastro de produtos.
* **CUSTOMER:** Catálogo público, visualização detalhada, cálculo de frete, fluxo de checkout e pedidos.

### 2. Painel Unificado de Operações
* **🛡️ Moderação de Lojistas:** Fila em tempo real para o Admin aprovar novas contas de vendedores.
* **📦 Anúncio de Produtos:** Cadastro ágil com precificação, controle de estoque inicial, imagens e categorias.
* **👤 Perfil & CPF:** Edição de dados cadastrais e vínculo de documento CPF para liquidações.
* **🔒 Gestão de Segurança:** Fluxo seguro de alteração de senha com confirmação e validação do hash atual.

---

## 🌐 Ambientes em Produção

* **Frontend:** [https://frontend-production-3603.up.railway.app](https://frontend-production-3603.up.railway.app)
* **API Backend:** [https://pleasant-light-production.up.railway.app](https://pleasant-light-production.up.railway.app)

---

## 🔑 Credenciais Padrão do Administrador

* **E-mail:** `admin@nexuscommerce.com`
* **Senha Inicial:** `NexusAdmin123`
