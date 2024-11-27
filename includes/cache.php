<?php
/**
 * Funções de gerenciamento de cache do WPKanban
 */

// Evitar acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Limpa todos os caches relacionados ao WPKanban
 */
function wpkanban_flush_all_caches() {
    // WordPress Object Cache
    wp_cache_flush();
    
    // Transients
    wpkanban_clear_transients();
    
    // Assets Version
    wpkanban_update_assets_version();
    
    // Opções
    wpkanban_clear_options_cache();
    
    // Banco de dados
    wpkanban_clear_db_cache();
}

/**
 * Limpa todos os transients do plugin
 */
function wpkanban_clear_transients() {
    global $wpdb;
    
    $transients = array(
        'wpkanban_waiting_list',
        'wpkanban_column_counts',
        'wpkanban_board_data'
    );
    
    foreach ($transients as $transient) {
        delete_transient($transient);
    }
    
    // Limpar transients expirados
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_wpkanban%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_wpkanban%'");
}

/**
 * Atualiza a versão dos assets
 */
function wpkanban_update_assets_version() {
    $version = time();
    update_option('wpkanban_assets_version', $version);
    return $version;
}

/**
 * Limpa cache de opções
 */
function wpkanban_clear_options_cache() {
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    
    $options = array(
        'wpkanban_refresh_interval',
        'wpkanban_assets_version'
    );
    
    foreach ($options as $option) {
        wp_cache_delete($option, 'options');
    }
}

/**
 * Limpa cache do banco de dados
 */
function wpkanban_clear_db_cache() {
    global $wpdb;
    
    // Limpar cache de consultas
    $wpdb->queries = array();
    
    // Limpar cache de resultados
    if (is_object($wpdb->results)) {
        $wpdb->results = null;
    }
    
    // Limpar cache de colunas
    $wpdb->col_info = array();
}

/**
 * Obtém a versão atual dos assets
 */
function wpkanban_get_assets_version() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return WPKANBAN_VERSION . '.' . time();
    }
    
    $version = get_option('wpkanban_assets_version');
    if (!$version) {
        $version = wpkanban_update_assets_version();
    }
    
    return WPKANBAN_VERSION . '.' . $version;
}

/**
 * Verifica se o cache precisa ser limpo
 */
function wpkanban_maybe_clear_cache() {
    $last_clear = get_option('wpkanban_last_cache_clear');
    $interval = 3600; // 1 hora
    
    if (!$last_clear || (time() - $last_clear) > $interval) {
        wpkanban_flush_all_caches();
        update_option('wpkanban_last_cache_clear', time());
    }
}

// Agendar limpeza automática de cache
if (!wp_next_scheduled('wpkanban_auto_clear_cache')) {
    wp_schedule_event(time(), 'hourly', 'wpkanban_auto_clear_cache');
}
add_action('wpkanban_auto_clear_cache', 'wpkanban_maybe_clear_cache');
