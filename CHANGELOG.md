# Changelog

Todos as principais mudanças neste projeto serão documentados neste arquivo, seguindo o [Versionamento Semântico](https://semver.org/).

## [1.0.0] - 2025-05-23
### 🚀 Lançamento Inicial
**Recursos Implementados:**
- Integração completa com WooCommerce via webhooks
- Envio automático de eventos para Pixel X:
  - `Purchase` (aprovado/aguardando pagamento)
  - `Lead` (formulários de checkout)
  - `AbandonedCart` (pedidos cancelados)
- Suporte aos parâmetros de rastreamento:
  - `fbc` formatado conforme especificação Meta
  - `fbp` gerado automaticamente
  - Campos `src` e `sck` via metadados
- Página de configuração no admin do WordPress:
  - Campos para URL e Token do Pixel X
- Sistema de logs em `/wp-content/webhook-logs/`
- Compatibilidade declarada com HPOS (WooCommerce 8.0+)

### 🔧 Correções
- Validação de segurança para evitar chamadas diretas
- Tratamento de erros em chamadas de API

### 📦 Dependências
- Requer PHP 8.0+ e WooCommerce 6.0+

---

> **Nota:** Este projeto adota o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).
