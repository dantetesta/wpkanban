<?php
/**
 * Plugin Name: WPKanban
 * Plugin URI: https://seu-site.com/wpkanban
 * Description: Sistema Kanban para gerenciamento de leads
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seu-site.com
 * Text Domain: wpkanban
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPKANBAN_VERSION')) {
    define('WPKANBAN_VERSION', '1.0.0');
}

if (!defined('WPKANBAN_FILE')) {
    define('WPKANBAN_FILE', __FILE__);
}

define('WPKANBAN_PLUGIN_DIR', plugin_dir_path(WPKANBAN_FILE));
define('WPKANBAN_PLUGIN_URL', plugin_dir_url(WPKANBAN_FILE));

// Verificar dependência do JetEngine
function wpkanban_check_dependencies() {
    if (!class_exists('Jet_Engine')) {
        add_action('admin_notices', 'wpkanban_dependency_notice');
        return false;
    }
    return true;
}

function wpkanban_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('O plugin WPKanban requer o JetEngine ativo para funcionar.', 'wpkanban'); ?></p>
    </div>
    <?php
}

// Inicialização do plugin
function wpkanban_init() {
    if (!class_exists('Jet_Engine')) {
        add_action('admin_notices', 'wpkanban_missing_jetengine_notice');
        return;
    }

    // Carregar arquivos na ordem correta
    require_once WPKANBAN_PLUGIN_DIR . 'includes/functions.php';  // Primeiro carrega as funções base
    require_once WPKANBAN_PLUGIN_DIR . 'includes/cache.php';      // Depois o sistema de cache
    require_once WPKANBAN_PLUGIN_DIR . 'includes/admin-menu.php'; // Menu e configurações
    require_once WPKANBAN_PLUGIN_DIR . 'includes/ajax-handlers.php'; // Handlers AJAX
    require_once WPKANBAN_PLUGIN_DIR . 'includes/init.php';       // Por último a inicialização
}
add_action('plugins_loaded', 'wpkanban_init');

// Registrar scripts e estilos
function wpkanban_enqueue_scripts($hook) {
    // Só carregar nas páginas do plugin
    if (strpos($hook, 'wpkanban') === false) {
        return;
    }

    wp_enqueue_style(
        'wpkanban-style',
        WPKANBAN_PLUGIN_URL . 'assets/css/style.css',
        array(),
        WPKANBAN_VERSION
    );

    wp_enqueue_style(
        'wpkanban-kanban',
        WPKANBAN_PLUGIN_URL . 'assets/css/kanban.css',
        array('wpkanban-style'),
        WPKANBAN_VERSION
    );

    wp_enqueue_script(
        'wpkanban-script',
        WPKANBAN_PLUGIN_URL . 'assets/js/kanban.js',
        array('jquery', 'jquery-ui-sortable'),
        WPKANBAN_VERSION,
        true
    );

    // Localizar script
    wp_localize_script('wpkanban-script', 'wpkanban', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpkanban_nonce'),
        'refresh_interval' => get_option('wpkanban_refresh_interval', 20),
        'initial_stage' => get_option('wpkanban_initial_stage', ''),
        'messages' => array(
            'error' => __('Erro ao processar requisição', 'wpkanban'),
            'success' => __('Operação realizada com sucesso', 'wpkanban')
        )
    ));
}
add_action('admin_enqueue_scripts', 'wpkanban_enqueue_scripts');

// Ativação do plugin
register_activation_hook(WPKANBAN_FILE, 'wpkanban_activate');
function wpkanban_activate() {
    if (!wpkanban_check_dependencies()) {
        deactivate_plugins(plugin_basename(WPKANBAN_FILE));
        wp_die(__('O plugin WPKanban requer o JetEngine ativo para funcionar.', 'wpkanban'));
    }
    
    // Configurar opções padrão
    add_option('wpkanban_refresh_interval', 20);
}

// Desativação do plugin
register_deactivation_hook(WPKANBAN_FILE, 'wpkanban_deactivate');
function wpkanban_deactivate() {
    // Limpar opções se necessário
    // delete_option('wpkanban_refresh_interval');
}
