<?php
defined('ABSPATH') || exit;

class PixelX_Resend_Handler {

    public function __construct() {
        // Botão individual
        add_action('woocommerce_admin_order_actions_end', [$this, 'add_resend_button'], 10, 1);
        
        // Bulk actions
        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_action'], 10, 3);
        
        // Admin notices
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Handler
        add_action('admin_post_pixelx_resend_webhook', [$this, 'handle_resend_webhook']);
        
        // CSS
        add_action('admin_head', [$this, 'admin_styles']);
    }

    public function add_resend_button($order) {
        $url = wp_nonce_url(
            admin_url("admin-post.php?action=pixelx_resend_webhook&order_id=" . $order->get_id()),
            'pixelx_resend_webhook'
        );
        
        echo sprintf(
            '<a class="button pixelx-resend-button" href="%s" title="%s">%s</a>',
            $url,
            __('Reenviar status para Pixel X', 'pixelx-woocommerce'),
            __('Reenviar para Pixel X', 'pixelx-woocommerce')
        );
    }

    public function handle_resend_webhook() {
        // Verifica segurança
        if (!current_user_can('edit_shop_orders') || !wp_verify_nonce($_REQUEST['_wpnonce'], 'pixelx_resend_webhook')) {
            wp_die(__('Ação não permitida', 'pixelx-woocommerce'));
        }
    
        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);
    
        if (!$order) {
            wp_die(__('Pedido não encontrado', 'pixelx-woocommerce'));
        }
    
        // Dispara o webhook com o status atual
        $current_status = $order->get_status();
        pixelx_send_webhook($order_id, 'manual_resend', $current_status, $order);
    
        // Redireciona de volta com mensagem
        wp_redirect(add_query_arg(
            'pixelx_message', 
            urlencode('Webhook reenviado com sucesso!'), 
            admin_url('post.php?post=' . $order_id . '&action=edit')
        ));
        exit;
    }

    public function admin_styles() {
        // ... (código CSS anterior)
        echo '<style>
        .pixelx-resend-button {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
            margin-left: 5px;
        }
        .pixelx-resend-button:hover {
            background: #3e8e41;
            border-color: #3e8e41;
        }
        </style>';
    }

    public function add_bulk_action($actions) {
        // ... (código bulk actions anterior)
    }

    public function handle_bulk_action($redirect_to, $action, $order_ids) {
        // ... (código bulk handler anterior)
    }

    public function show_admin_notices() {
        // ... (código de notificações anterior)
        if (!empty($_GET['pixelx_message'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html(urldecode($_GET['pixelx_message'])) . 
             '</p></div>';
        }
    }
}

new PixelX_Resend_Handler();
