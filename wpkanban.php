<?php
/**
 * Plugin Name: WPKanban
 * Plugin URI: https://wpkanban.com
 * Description: Plugin para gerenciamento de leads em formato kanban
 * Version: 1.0.0
 * Author: Dante Testa
 * Author URI: https://dantetesta.com
 * License: GPL2
 * Text Domain: wpkanban
 */

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definições do plugin
define('WPKANBAN_VERSION', '1.0.0');
define('WPKANBAN_FILE', __FILE__);
define('WPKANBAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPKANBAN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carrega os arquivos necessários
require_once WPKANBAN_PLUGIN_DIR . 'includes/post-types.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/activate.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/functions.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/cache.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/admin-menu.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once WPKANBAN_PLUGIN_DIR . 'includes/init.php';

// Registra hooks de ativação e desativação
register_activation_hook(__FILE__, 'wpkanban_activate');
register_deactivation_hook(__FILE__, 'wpkanban_deactivate');

// Verifica dependências
function wpkanban_check_dependencies() {
    return true;
}

// Registrar scripts e estilos
function wpkanban_enqueue_scripts($hook) {
    if ('toplevel_page_wpkanban' !== $hook) {
        return;
    }

    // jQuery UI
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-droppable');

    // Touch Punch para suporte mobile
    wp_enqueue_script(
        'jquery-ui-touch-punch',
        WPKANBAN_PLUGIN_URL . 'assets/js/jquery.ui.touch-punch.min.js',
        array('jquery-ui-sortable'),
        WPKANBAN_VERSION,
        true
    );

    // Script principal do kanban
    wp_enqueue_script(
        'wpkanban-js',
        WPKANBAN_PLUGIN_URL . 'assets/js/kanban.js',
        array('jquery', 'jquery-ui-sortable', 'jquery-ui-touch-punch'),
        WPKANBAN_VERSION,
        true
    );

    // Estilos
    wp_enqueue_style(
        'wpkanban-css',
        WPKANBAN_PLUGIN_URL . 'assets/css/kanban.css',
        array(),
        WPKANBAN_VERSION
    );

    // Localização
    wp_localize_script('wpkanban-js', 'wpkanban', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpkanban_nonce'),
        'strings' => array(
            'lead_moved' => __('Lead movido com sucesso!', 'wpkanban'),
            'error' => __('Erro ao mover o lead.', 'wpkanban'),
            'confirm_delete' => __('Tem certeza que deseja excluir este lead?', 'wpkanban'),
            'deleted' => __('Lead excluído com sucesso!', 'wpkanban'),
            'error_delete' => __('Erro ao excluir o lead.', 'wpkanban'),
            'saved' => __('Lead salvo com sucesso!', 'wpkanban'),
            'error_save' => __('Erro ao salvar o lead.', 'wpkanban')
        )
    ));
}
add_action('admin_enqueue_scripts', 'wpkanban_enqueue_scripts');

// Desativação do plugin
function wpkanban_deactivate() {
    // Limpar opções se necessário
    flush_rewrite_rules();
}
