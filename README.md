# WooCommerce Pixel X Integration

Plugin oficial para integração entre WooCommerce e Pixel X, enviando eventos de compra, leads e dados de rastreamento para o CAPI e Pixel do Meta.

## 📦 Recursos
- Envio automático de eventos: `Purchase`, `Lead`, `AddToCart`
- Suporte a **HPOS** (High-Performance Order Storage)
- Rastreamento completo com `fbc`, `fbp`, UTMs e parâmetros customizados (`src`, `sck`)
- Logs detalhados em `/wp-content/webhook-logs/`

## 🔄 Reenvio Manual de Webhooks

1. **Pedido individual**:
   - Acesse a tela de edição do pedido
   - Clique no botão "Reenviar para Pixel X" na barra de ações

2. **Ação em massa**:
   - Na lista de pedidos, selecione vários pedidos
   - Escolha "Reenviar para Pixel X" no menu de ações em massa

## ⚙️ Instalação
1. Baixe o [último release](https://github.com/fbaqui/woocommerce-pixelx-integration/releases)
2. Envie para `/wp-content/plugins/`
3. Ative em **Plugins > Instalados**

## 🔧 Configuração
Acesse **WooCommerce > Pixel X** e insira:
- URL do Webhook Pixel X
- Token de autenticação

## 📜 Licença
GNU GPLv3 | [Felipe Baqui](https://baquiebyte.eti.br)
