<?php
/**
 * Plugin Name: Pixel X for WooCommerce
 * Plugin URI: https://baquiebyte.eti.br/pixelx-woocommerce
 * Description: Integração avançada entre WooCommerce e Pixel X para rastreamento completo de conversões via Webhooks. Envie automaticamente todos os estados de pedidos para o Pixel X com dados de produtos, clientes e UTM.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Felipe Baqui
 * Author URI: https://baquiebyte.eti.br
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pixelx-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * @package PixelX_WooCommerce
 * @category Integration
 * @author Felipe Baqui
 */


// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Declara compatibilidade com HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

$log_dir = '/var/www/vpsd.com.br/wordpress/wp-content/webhook-logs/';
$log_file = $log_dir . 'pixelx_integracao.log';

// Função para registrar logs
function pixelx_log($message, $data = []) {
    global $log_file;

    if (!file_exists($log_file)) {
        @mkdir(dirname($log_file), 0755, true);
    }

    $timestamp = date('[Y-m-d H:i:s]');
    $log_message = "{$timestamp} {$message}\n";

    if (!empty($data)) {
        $log_message .= "DETALHES: " . print_r($data, true) . "\n";
    }

    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Formata o FBC conforme especificação da Meta
 * @param string $fbclid - O Facebook Click ID (do parâmetro fbclid)
 * @param string $event_time - Data do evento no formato Y-m-d H:i:s
 * @return string
 */
function pixelx_format_fbc($fbclid, $event_time) {
    if (empty($fbclid)) {
        return '';
    }

    // Converte a data do evento para timestamp em milissegundos
    $timestamp = strtotime($event_time) * 1000;

    // Remove caracteres inválidos do fbclid
    $clean_fbclid = preg_replace('/[^a-zA-Z0-9_-]/', '', $fbclid);

    return "fb.1.{$timestamp}.{$clean_fbclid}";
}

// 1. Página de Configurações
add_action('admin_menu', 'pixelx_add_admin_menu');
function pixelx_add_admin_menu() {
    add_menu_page(
        'Configurações Pixel X',
        'Pixel X 4 WooCommerce',
        'manage_options',
        'pixelx-settings',
        'pixelx_admin_page',
        'dashicons-chart-line',
        100
    );
}

// 2. Campos de Configuração (Token e URL)
function pixelx_admin_page() {
    ?>
    <div class="wrap">
        <h1>Configurações do Pixel X</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('pixelx_settings');
            do_settings_sections('pixelx-settings');
            submit_button('Salvar', 'primary', 'submit', false);
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'pixelx_settings_init');
function pixelx_settings_init() {
    register_setting('pixelx_settings', 'pixelx_webhook_url');
    register_setting('pixelx_settings', 'pixelx_webhook_token');

    add_settings_section(
        'pixelx_section',
        'Credenciais do Webhook',
        null,
        'pixelx-settings'
    );

    add_settings_field(
        'pixelx_webhook_url',
        'URL do Webhook',
        'pixelx_webhook_url_callback',
        'pixelx-settings',
        'pixelx_section'
    );

    add_settings_field(
        'pixelx_webhook_token',
        'Token do Webhook',
        'pixelx_webhook_token_callback',
        'pixelx-settings',
        'pixelx_section'
    );
}

function pixelx_webhook_url_callback() {
    $url = esc_attr(get_option('pixelx_webhook_url'));
    echo "<input type='text' name='pixelx_webhook_url' value='{$url}' class='regular-text' placeholder='https://seu-endpoint.pixelx.app'>";
}

function pixelx_webhook_token_callback() {
    $token = esc_attr(get_option('pixelx_webhook_token'));
    echo "<input type='text' name='pixelx_webhook_token' value='{$token}' class='regular-text' placeholder='0a21c9f5-dac4-4f45-a430-9e2eb20591ec'>";
}

// 3. Disparar Webhook para Todos os Status
// Envia webhook tanto na criação quanto na alteração de status
add_action('woocommerce_new_order', 'pixelx_send_new_order_webhook', 10, 2);
add_action('woocommerce_order_status_changed', 'pixelx_send_webhook', 10, 4);

function pixelx_send_new_order_webhook($order_id, $order) {
    // Força o envio mesmo sendo o status inicial
    pixelx_send_webhook($order_id, 'new', 'pending', $order);
}

function pixelx_send_webhook($order_id, $old_status, $new_status, $order) {
    // Se for um pedido novo, $old_status virá como 'new'
    $is_new_order = ($old_status === 'new');
    
    // Não envia se for uma atualização para o mesmo status (exceto para novos pedidos)
    if (!$is_new_order && $old_status === $new_status) {
        return;
    }
    
    $webhook_url = get_option('pixelx_webhook_url');
    $token = get_option('pixelx_webhook_token');

    if (empty($webhook_url)) {
        pixelx_log("ERRO: URL do webhook não configurada", ['order_id' => $order_id]);
        return;
    }

    if (empty($token)) {
        pixelx_log("ERRO: Token do webhook não configurado", ['order_id' => $order_id]);
        return;
    }

    // Mapeamento de status (conforme sua tabela)
    $status_map = [
        'pending'    => 'waiting_payment',
        'processing' => 'waiting_payment',
        'on-hold'    => 'waiting_payment',
        'completed'  => 'approved',
        'cancelled'  => 'canceled', // estava 'abandoned_cart' mas o pixelX tratava como initiateCheckout
        'refunded'   => 'refund',
        'failed'     => 'canceled'
    ];

    // Dados do pedido
    $product_id = '';
    $product_name = '';
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product_name = $item->get_name();
        break; // Pega apenas o primeiro produto
    }

    $fbclid = strpos($order->get_meta('_wc_order_attribution_session_entry'), 'fbclid=') !== false ?
        explode('fbclid=', $order->get_meta('_wc_order_attribution_session_entry'))[1] : '';


    // Montar payload
    $payload = [
        'token' => $token,
        'event' => [
            'status' => $status_map[$new_status] ?? $new_status,
            'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'url' => $order->get_checkout_order_received_url(),
            'product_id' => $product_id,
            'product_name' => $product_name,
            'value' => $order->get_total(),
            //'currency' => $order->get_currency()
        ],
        'lead' => [
            'id' => $order->get_customer_id(),
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'ip' => $order->get_customer_ip_address(),
            'user_agent' => $order->get_customer_user_agent()
        ]
        /*,
        'tracking' => [
            'utm_source' => $order->get_meta('_wc_order_attribution_utm_source'),
            'utm_medium' => $order->get_meta('_wc_order_attribution_utm_medium'),
            'utm_campaign' => $order->get_meta('_wc_order_attribution_utm_campaign'),
            //'fbc' => pixelx_format_fbc($fbclid, $order->get_date_created()->format('Y-m-d H:i:s'))
        ]*/
    ];

    // Enviar via POST
    $args = [
        'body' => json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 15
    ];

    pixelx_log("Enviando webhook para Pixel X", [
        'order_id' => $order_id,
        'status' => $new_status,
        'payload' => $payload
    ]);

    $response = wp_remote_post($webhook_url, $args);

    if (is_wp_error($response)) {
        pixelx_log("FALHA no envio para Pixel X", [
            'order_id' => $order_id,
            'error' => $response->get_error_message()
        ]);
    } else {
        pixelx_log("Webhook enviado com SUCESSO", [
            'order_id' => $order_id,
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_body' => wp_remote_retrieve_body($response)
        ]);
    }
}
