<?php
if (!defined('ABSPATH')) {
    exit;
}

// Adicionar menu no admin
function wpkanban_add_admin_menu() {
    add_menu_page(
        'WPKanban',
        'WPKanban',
        'manage_options',
        'wpkanban',
        'wpkanban_render_kanban_page',
        'dashicons-schedule',
        30
    );
    
    add_submenu_page(
        'wpkanban',
        'Configurações',
        'Configurações',
        'manage_options',
        'wpkanban-settings',
        'wpkanban_render_settings_page'
    );
    
    // Submenu Etapas
    add_submenu_page(
        'wpkanban',
        'Etapas',
        'Etapas',
        'manage_options',
        'edit-tags.php?taxonomy=etapas&post_type=leads'
    );

    // Submenu Interesses
    add_submenu_page(
        'wpkanban',
        'Interesses',
        'Interesses',
        'manage_options',
        'edit-tags.php?taxonomy=interesse&post_type=leads'
    );
}
add_action('admin_menu', 'wpkanban_add_admin_menu');

// Remove o submenu duplicado
function wpkanban_remove_duplicate_submenu() {
    remove_submenu_page('edit.php?post_type=leads', 'edit-tags.php?taxonomy=etapas&amp;post_type=leads');
    remove_submenu_page('edit.php?post_type=leads', 'edit-tags.php?taxonomy=interesse&amp;post_type=leads');
}
add_action('admin_menu', 'wpkanban_remove_duplicate_submenu', 999);

// Ajusta os links do menu quando ativos
function wpkanban_fix_parent_menu($parent_file) {
    global $current_screen;
    
    if ($current_screen->taxonomy == 'etapas' || $current_screen->taxonomy == 'interesse') {
        $parent_file = 'wpkanban';
    }
    
    return $parent_file;
}
add_filter('parent_file', 'wpkanban_fix_parent_menu');

// Registrar configurações
function wpkanban_register_settings() {
    register_setting('wpkanban_settings', 'wpkanban_refresh_interval', array(
        'type' => 'integer',
        'default' => 20,
        'sanitize_callback' => function($value) {
            return max(5, min(300, intval($value)));
        }
    ));
}
add_action('admin_init', 'wpkanban_register_settings');

// Adicionar scripts e estilos no admin
function wpkanban_admin_enqueue_scripts($hook) {
    if ($hook === 'wpkanban_page_wpkanban-settings') {
        // Sortable.js para drag and drop
        wp_enqueue_script('sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', array(), '1.15.0', true);
        
        // Scripts e estilos do admin
        wp_enqueue_style('wpkanban-admin-settings', plugins_url('assets/css/admin-settings.css', dirname(__FILE__)));
        wp_enqueue_script('wpkanban-admin-settings', plugins_url('assets/js/admin-settings.js', dirname(__FILE__)), array('jquery', 'sortablejs'), '1.0', true);
        
        // Localize script
        wp_localize_script('wpkanban-admin-settings', 'wpkanbanSettings', array(
            'nonce' => wp_create_nonce('wpkanban_save_etapas_order')
        ));
    }
    
    wp_enqueue_script('kanban-js', plugins_url('assets/js/kanban.js', __FILE__), array('jquery'), WPKANBAN_VERSION, true);
    wp_localize_script('kanban-js', 'wpkanbanSettings', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpkanban_nonce'),
        'intervalo' => get_option('wpkanban_refresh_interval', 20) // Valor padrão de 20 segundos
    ));
}
add_action('admin_enqueue_scripts', 'wpkanban_admin_enqueue_scripts');

// Renderizar página principal
function wpkanban_render_kanban_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Incluir função de renderização do board
    require_once WPKANBAN_PLUGIN_DIR . 'includes/init.php';
    if (function_exists('wpkanban_render_board')) {
        ?>
        <div class="wrap">
            <h1>WPKanban - Gerenciamento de Leads</h1>
            <?php 
            wpkanban_render_board();
            ?>
        </div>
        <?php
    }
}

// Renderizar página de configurações
function wpkanban_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Busca todas as etapas ordenadas
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
    
    $refresh_interval = get_option('wpkanban_refresh_interval', 20);
    ?>
    <div class="wrap">
        
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('wpkanban_settings');
            do_settings_sections('wpkanban_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpkanban_refresh_interval">
                            Intervalo de Atualização (segundos)
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="wpkanban_refresh_interval"
                               name="wpkanban_refresh_interval" 
                               value="<?php echo esc_attr($refresh_interval); ?>"
                               min="5" 
                               max="300" 
                               step="1" />
                        <p class="description">
                            Intervalo de atualização automática das listas (mínimo: 5s, máximo: 300s)
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="wpkanban-etapas-card">
                <h3>Ordenar Etapas</h3>
                <p>Arraste e solte as etapas para definir a ordem em que elas aparecerão no quadro Kanban.</p>
                
                <ul id="wpkanban-etapas-sort" class="wpkanban-etapas-list">
                    <?php foreach ($etapas as $etapa) : ?>
                        <li class="wpkanban-etapa-item" data-term-id="<?php echo esc_attr($etapa->term_id); ?>">
                            <span class="dashicons dashicons-menu"></span>
                            <?php echo esc_html($etapa->name); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Handler AJAX para salvar a ordem das etapas
function wpkanban_ajax_save_etapas_order() {
    // Verifica o nonce
    if (!check_ajax_referer('wpkanban_save_etapas_order', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Verifica permissões
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Salva a nova ordem
    if (isset($_POST['ordem']) && is_array($_POST['ordem'])) {
        foreach ($_POST['ordem'] as $item) {
            if (isset($item['term_id']) && isset($item['order'])) {
                update_term_meta(intval($item['term_id']), 'order', intval($item['order']));
            }
        }
        wp_send_json_success();
    } else {
        wp_send_json_error('Invalid data');
    }
}
add_action('wp_ajax_wpkanban_save_etapas_order', 'wpkanban_ajax_save_etapas_order');
