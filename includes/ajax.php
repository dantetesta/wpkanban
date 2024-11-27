<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handler para buscar dados do lead
function wpkanban_ajax_get_lead_data() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    if (!$lead_id) {
        wp_send_json_error('ID do lead inválido');
    }
    
    $lead_data = wpkanban_get_lead_data($lead_id);
    if (!$lead_data) {
        wp_send_json_error('Lead não encontrado');
    }
    
    wp_send_json_success($lead_data);
}
add_action('wp_ajax_wpkanban_get_lead_data', 'wpkanban_ajax_get_lead_data');

// Handler para atualizar lead
function wpkanban_ajax_update_lead() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    if (!$lead_id) {
        wp_send_json_error('ID do lead inválido');
    }
    
    $data = array(
        'nome' => sanitize_text_field($_POST['nome']),
        'email' => sanitize_email($_POST['email']),
        'whatsapp' => sanitize_text_field($_POST['whatsapp']),
        'interesse' => sanitize_text_field($_POST['interesse']),
        'status' => sanitize_text_field($_POST['status']),
        'anotacoes' => sanitize_textarea_field($_POST['anotacoes'])
    );
    
    $updated = wpkanban_update_lead($lead_id, $data);
    if (is_wp_error($updated)) {
        wp_send_json_error($updated->get_error_message());
    }
    
    wp_send_json_success();
}
add_action('wp_ajax_wpkanban_update_lead', 'wpkanban_ajax_update_lead');

// Handler para atualizar estágio do lead
function wpkanban_ajax_update_lead_stage() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $stage_id = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
    
    if (!$lead_id || !$stage_id) {
        wp_send_json_error('Parâmetros inválidos');
    }
    
    $result = wp_set_object_terms($lead_id, array($stage_id), 'etapas');
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success();
}
add_action('wp_ajax_wpkanban_update_lead_stage', 'wpkanban_ajax_update_lead_stage');

// Handler para recarregar card do lead
function wpkanban_ajax_refresh_card() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    if (!$lead_id) {
        wp_send_json_error('ID do lead inválido');
    }
    
    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        wp_send_json_error('Lead não encontrado');
    }
    
    ob_start();
    wpkanban_render_lead_card($lead);
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
}
add_action('wp_ajax_wpkanban_refresh_card', 'wpkanban_ajax_refresh_card');
