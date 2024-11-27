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
        'wpkanban_render_main_page',
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
}
add_action('admin_menu', 'wpkanban_add_admin_menu');

// Registrar configurações
function wpkanban_register_settings() {
    register_setting('wpkanban_settings', 'wpkanban_refresh_interval', array(
        'type' => 'integer',
        'default' => 20,
        'sanitize_callback' => function($value) {
            return max(5, min(300, intval($value)));
        }
    ));
    
    register_setting('wpkanban_settings', 'wpkanban_initial_stage', array(
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ));
}
add_action('admin_init', 'wpkanban_register_settings');

// Renderizar página principal
function wpkanban_render_main_page() {
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
    
    $refresh_interval = get_option('wpkanban_refresh_interval', 20);
    $initial_stage = get_option('wpkanban_initial_stage', '');
    
    // Buscar todas as etapas
    $etapas = get_terms(array(
        'taxonomy' => 'etapas',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    ?>
    <div class="wrap">
        <h1>Configurações do WPKanban</h1>
        
        <form method="post" action="options.php">
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
                <tr>
                    <th scope="row">
                        <label for="wpkanban_initial_stage">
                            Etapa Inicial do Funil
                        </label>
                    </th>
                    <td>
                        <select id="wpkanban_initial_stage" 
                                name="wpkanban_initial_stage">
                            <option value="">Selecione uma etapa</option>
                            <?php foreach ($etapas as $etapa) : ?>
                                <option value="<?php echo esc_attr($etapa->slug); ?>" 
                                    <?php selected($initial_stage, $etapa->slug); ?>>
                                    <?php echo esc_html($etapa->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Selecione qual etapa será monitorada para novos leads
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
