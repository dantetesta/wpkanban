<?php

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Registra o CPT Leads
function wpkanban_register_leads_post_type() {
    $labels = array(
        'name'               => 'Leads',
        'singular_name'      => 'Lead',
        'menu_name'          => 'Leads',
        'add_new'           => 'Adicionar Novo',
        'add_new_item'      => 'Adicionar Novo Lead',
        'edit_item'         => 'Editar Lead',
        'new_item'          => 'Novo Lead',
        'view_item'         => 'Ver Lead',
        'search_items'      => 'Buscar Leads',
        'not_found'         => 'Nenhum lead encontrado',
        'not_found_in_trash'=> 'Nenhum lead encontrado na lixeira'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'leads'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title'),
        'menu_icon'          => 'dashicons-groups'
    );

    register_post_type('leads', $args);
}
add_action('init', 'wpkanban_register_leads_post_type');

// Registra a taxonomia Etapas
function wpkanban_register_etapas_taxonomy() {
    $labels = array(
        'name'              => 'Etapas',
        'singular_name'     => 'Etapa',
        'search_items'      => 'Buscar Etapas',
        'all_items'         => 'Todas as Etapas',
        'edit_item'         => 'Editar Etapa',
        'update_item'       => 'Atualizar Etapa',
        'add_new_item'      => 'Adicionar Nova Etapa',
        'new_item_name'     => 'Nova Etapa',
        'menu_name'         => 'Etapas'
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'etapa'),
        'show_in_rest'      => true
    );

    register_taxonomy('etapas', array('leads'), $args);
    
    // Registra o meta campo de ordem
    register_term_meta('etapas', 'order', array(
        'type' => 'integer',
        'single' => true,
        'show_in_rest' => true,
        'default' => 0
    ));
}
add_action('init', 'wpkanban_register_etapas_taxonomy');

// Removendo a função customizada do meta box
remove_filter('add_meta_boxes', 'wpkanban_etapas_meta_box');

// Adiciona os meta boxes dos campos
function wpkanban_add_lead_meta_boxes() {
    // Meta box para dados do lead
    add_meta_box(
        'wpkanban_lead_info',
        'Informações do Lead',
        'wpkanban_lead_info_callback',
        'leads',
        'normal',
        'high'
    );

    // Meta box para interesse
    add_meta_box(
        'wpkanban_interesse',
        'Interesse',
        'wpkanban_interesse_meta_box_callback',
        'leads',
        'normal',
        'high'
    );

    // Meta box para anotações
    add_meta_box(
        'wpkanban_anotacoes',
        'Anotações',
        'wpkanban_anotacoes_callback',
        'leads',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'wpkanban_add_lead_meta_boxes');

// Callback para o meta box de informações
function wpkanban_lead_info_callback($post) {
    wp_nonce_field('wpkanban_save_lead_meta', 'wpkanban_lead_meta_nonce');

    $nome = get_post_meta($post->ID, 'nome', true);
    $email = get_post_meta($post->ID, 'email', true);
    $whatsapp = get_post_meta($post->ID, 'whatsapp', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="nome">Nome</label></th>
            <td>
                <input type="text" id="nome" name="nome" value="<?php echo esc_attr($nome); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="email">Email</label></th>
            <td>
                <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="whatsapp">WhatsApp</label></th>
            <td>
                <input type="text" id="whatsapp" name="whatsapp" value="<?php echo esc_attr($whatsapp); ?>" class="regular-text">
            </td>
        </tr>
    </table>
    <?php
}

// Callback para o meta box de interesse
function wpkanban_interesse_meta_box_callback($post) {
    wp_nonce_field('wpkanban_save_lead_meta', 'wpkanban_lead_meta_nonce');
    
    // Busca o valor salvo
    $interesse = get_post_meta($post->ID, 'interesse', true);
    
    // Define as opções disponíveis
    $opcoes = array(
        '' => 'Selecione...',
        'Comprar' => 'Comprar',
        'Vender' => 'Vender',
        'Alugar' => 'Alugar'
    );
    
    ?>
    <select name="interesse" id="interesse" style="width: 100%">
        <?php foreach ($opcoes as $valor => $label) : ?>
            <option value="<?php echo esc_attr($valor); ?>" <?php selected($interesse, $valor); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Callback para o meta box de anotações
function wpkanban_anotacoes_callback($post) {
    $anotacoes = get_post_meta($post->ID, 'anotacoes', true);
    wp_editor($anotacoes, 'anotacoes', array(
        'textarea_name' => 'anotacoes',
        'media_buttons' => false,
        'textarea_rows' => 10,
        'teeny' => true
    ));
}

// Salva os meta boxes
function wpkanban_save_lead_meta($post_id) {
    // Verifica o nonce
    if (!isset($_POST['wpkanban_lead_meta_nonce']) || 
        !wp_verify_nonce($_POST['wpkanban_lead_meta_nonce'], 'wpkanban_save_lead_meta')) {
        return;
    }

    // Verifica se é autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Verifica as permissões
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Verifica se é o post type correto
    if (get_post_type($post_id) !== 'leads') {
        return;
    }

    // Array com os campos e suas funções de sanitização
    $campos = array(
        'nome' => 'sanitize_text_field',
        'email' => 'sanitize_email',
        'whatsapp' => 'sanitize_text_field',
        'interesse' => 'sanitize_text_field',
        'anotacoes' => 'wp_kses_post'
    );

    // Salva cada campo
    foreach ($campos as $campo => $sanitize_callback) {
        if (isset($_POST[$campo])) {
            $valor = call_user_func($sanitize_callback, $_POST[$campo]);
            update_post_meta($post_id, $campo, $valor);
        }
    }
}
add_action('save_post', 'wpkanban_save_lead_meta');
