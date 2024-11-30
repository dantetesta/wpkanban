<?php
if (!defined('ABSPATH')) {
    exit;
}

// Registrar scripts e estilos
function wpkanban_register_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    
    // jQuery UI
    wp_register_script(
        'jquery-ui-touch-punch',
        $plugin_url . 'assets/js/jquery.ui.touch-punch.min.js',
        array('jquery-ui-sortable'),
        '0.2.3',
        true
    );
    
    // Plugin assets
    wp_register_style(
        'wpkanban-style',
        $plugin_url . 'assets/css/style.css',
        array(),
        WPKANBAN_VERSION
    );
    
    wp_register_script(
        'wpkanban-script',
        $plugin_url . 'assets/js/kanban.js',
        array('jquery', 'jquery-ui-sortable', 'jquery-ui-touch-punch'),
        WPKANBAN_VERSION,
        true
    );
}
add_action('admin_init', 'wpkanban_register_assets');

// Carregar assets na página do plugin
function wpkanban_enqueue_assets($hook) {
    if ('toplevel_page_wpkanban' !== $hook) {
        return;
    }
    
    // Gerar versão única para cache busting
    $version = defined('WP_DEBUG') && WP_DEBUG ? 
        WPKANBAN_VERSION . '.' . time() : 
        WPKANBAN_VERSION . '.' . get_option('wpkanban_assets_version', '1');
    
    // jQuery UI
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-droppable');
    
    // Touch Punch para suporte mobile
    wp_enqueue_script(
        'jquery-touch-punch',
        plugins_url('/assets/js/jquery.ui.touch-punch.min.js', WPKANBAN_FILE),
        array('jquery-ui-sortable'),
        $version,
        true
    );
    
    // Script principal do Kanban
    wp_enqueue_script(
        'wpkanban-js',
        plugins_url('/assets/js/kanban.js', WPKANBAN_FILE),
        array('jquery', 'jquery-ui-sortable', 'jquery-touch-punch'),
        $version,
        true
    );
    
    // CSS do Kanban
    wp_enqueue_style(
        'wpkanban-css',
        plugins_url('/assets/css/style.css', WPKANBAN_FILE),
        array(),
        $version
    );

    wp_enqueue_style(
        'wpkanban-kanban-css',
        plugins_url('/assets/css/kanban.css', WPKANBAN_FILE),
        array('wpkanban-css'),
        $version
    );
    
    // Localize script
    wp_localize_script('wpkanban-js', 'wpkanban', array(
        'nonce' => wp_create_nonce('wpkanban_nonce'),
        'refresh_interval' => get_option('wpkanban_refresh_interval', 20),
        'ajax_url' => admin_url('admin-ajax.php'),
        'version' => $version,
        'last_check' => current_time('mysql')
    ));
}
add_action('admin_enqueue_scripts', 'wpkanban_enqueue_assets');

// Renderizar board
function wpkanban_render_board() {
    // Buscar etapa inicial configurada
    $initial_stage_slug = get_option('wpkanban_initial_stage', '');
    
    // Buscar etapas
    $etapas = get_terms(array(
        'taxonomy' => 'etapas',
        'hide_empty' => false
    ));
    
    // Ordena as etapas pelo meta 'order', mantendo as sem ordem no final
    usort($etapas, function($a, $b) {
        $order_a = get_term_meta($a->term_id, 'order', true);
        $order_b = get_term_meta($b->term_id, 'order', true);
        
        // Se ambos têm ordem, compara normalmente
        if ($order_a !== '' && $order_b !== '') {
            return intval($order_a) - intval($order_b);
        }
        
        // Se apenas um tem ordem, ele vem primeiro
        if ($order_a !== '') return -1;
        if ($order_b !== '') return 1;
        
        // Se nenhum tem ordem, mantém a ordem alfabética
        return strcmp($a->name, $b->name);
    });
    
    // Se tiver etapa inicial configurada, reordenar array
    if (!empty($initial_stage_slug)) {
        $reordered = array();
        foreach ($etapas as $key => $etapa) {
            if ($etapa->slug === $initial_stage_slug) {
                array_unshift($reordered, $etapa);
                unset($etapas[$key]);
                break;
            }
        }
        $etapas = array_merge($reordered, array_values($etapas));
    }
    ?>
    <div id="wpkanban-board" class="wpkanban-board">
        <?php foreach ($etapas as $etapa) : 
            $is_initial = $etapa->slug === $initial_stage_slug;
            
            // Buscar leads desta etapa
            $args = array(
                'post_type' => 'leads',
                'posts_per_page' => 30,
                'orderby' => 'date',
                'order' => 'DESC',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'etapas',
                        'field' => 'term_id',
                        'terms' => $etapa->term_id
                    )
                )
            );
            $query = new WP_Query($args);
            $leads = $query->posts;
            $total_leads = $query->found_posts;
        ?>
            <div class="wpkanban-column" 
                 data-stage-id="<?php echo esc_attr($etapa->term_id); ?>"
                 data-initial="<?php echo $is_initial ? '1' : '0'; ?>">
                <div class="column-header">
                    <h2><?php echo esc_html($etapa->name); ?></h2>
                    <span class="lead-count"><?php echo esc_html($total_leads); ?></span>
                </div>
                
                <div class="column-content" data-page="1" data-has-more="<?php echo $query->max_num_pages > 1 ? 'true' : 'false'; ?>">
                    <?php
                    foreach ($leads as $lead) {
                        wpkanban_render_lead_card($lead);
                    }
                    
                    if ($query->max_num_pages > 1) {
                        echo '<div class="loading-more">Carregando mais leads...</div>';
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Usar a função do modal já definida em functions.php
    wpkanban_render_edit_modal();
}

// Função para limpar cache do WordPress
function wpkanban_clear_cache() {
    // Atualizar versão dos assets
    update_option('wpkanban_assets_version', time());
    
    // Limpar cache do WordPress
    wp_cache_flush();
    
    // Limpar cache de objetos
    wp_cache_delete('wpkanban_board_data', 'wpkanban');
    
    // Limpar cache de transients
    delete_transient('wpkanban_waiting_list');
    delete_transient('wpkanban_column_counts');
    
    // Forçar atualização do banco
    wp_schedule_single_event(time(), 'wpkanban_cleanup_cache');
}

// Registrar hook de limpeza de cache
add_action('wpkanban_cleanup_cache', 'wpkanban_clear_cache');

// Limpar cache na ativação do plugin
register_activation_hook(WPKANBAN_FILE, 'wpkanban_clear_cache');

// Adicionar botão de limpar cache na página de configurações
function wpkanban_add_clear_cache_button($links) {
    $clear_cache = array(
        '<a href="' . wp_nonce_url(admin_url('admin.php?page=wpkanban&clear_cache=1'), 'wpkanban_clear_cache') . '">' . __('Limpar Cache', 'wpkanban') . '</a>'
    );
    return array_merge($links, $clear_cache);
}
add_filter('plugin_action_links_' . plugin_basename(WPKANBAN_FILE), 'wpkanban_add_clear_cache_button');

// Processar limpeza de cache via admin
function wpkanban_process_cache_clear() {
    if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == 1) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para executar esta ação.'));
        }
        
        check_admin_referer('wpkanban_clear_cache');
        
        wpkanban_clear_cache();
        
        wp_redirect(add_query_arg(array(
            'page' => 'wpkanban',
            'cache_cleared' => 1
        ), admin_url('admin.php')));
        exit;
    }
}
add_action('admin_init', 'wpkanban_process_cache_clear');

// Mostrar notificação de cache limpo
function wpkanban_admin_notices() {
    if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] == 1) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Cache do WPKanban limpo com sucesso!', 'wpkanban'); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'wpkanban_admin_notices');
