<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handler para verificar novos leads
function wpkanban_check_new_leads_handler() {
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    $last_check = isset($_POST['last_check']) ? sanitize_text_field($_POST['last_check']) : '';
    $current_time = current_time('mysql');

    // Buscar leads novos
    $args = array(
        'post_type' => 'leads',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => array(
            array(
                'taxonomy' => 'etapas',
                'field' => 'slug',
                'terms' => 'aguardando'
            )
        )
    );

    // Se temos um último check, adicionar filtro de data
    if ($last_check) {
        $args['date_query'] = array(
            array(
                'after' => $last_check,
                'inclusive' => false
            )
        );
    }

    $leads = get_posts($args);
    $new_leads_count = count($leads);

    // Se temos novos leads, gerar HTML
    $html = '';
    if ($new_leads_count > 0) {
        ob_start();
        foreach ($leads as $lead) {
            wpkanban_render_lead_card($lead);
        }
        $html = ob_get_clean();
    }

    wp_send_json_success(array(
        'has_new_leads' => $new_leads_count > 0,
        'new_leads_count' => $new_leads_count,
        'html' => $html,
        'timestamp' => $current_time
    ));
}
add_action('wp_ajax_wpkanban_check_new_leads', 'wpkanban_check_new_leads_handler');

// Handler para atualizar estágio do lead
function wpkanban_update_lead_stage_handler() {
    // Verificar nonce
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    // Verificar permissões
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    // Obter e validar parâmetros
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $stage_id = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;

    if (!$lead_id || !$stage_id) {
        wp_send_json_error(array('message' => 'Parâmetros inválidos'));
    }

    // Verificar se o lead existe
    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        wp_send_json_error(array('message' => 'Lead não encontrado'));
    }

    // Verificar se o estágio existe
    $stage = get_term($stage_id, 'etapas');
    if (!$stage || is_wp_error($stage)) {
        wp_send_json_error(array('message' => 'Estágio não encontrado'));
    }

    // Atualizar o estágio do lead
    $result = wp_set_object_terms($lead_id, array($stage_id), 'etapas');
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => 'Erro ao atualizar estágio'));
    }

    // Registrar a mudança no histórico
    $old_stages = wp_get_object_terms($lead_id, 'etapas', array('fields' => 'names'));
    $old_stage = !empty($old_stages) ? $old_stages[0] : 'Sem estágio';
    
    add_post_meta($lead_id, 'stage_history', array(
        'from' => $old_stage,
        'to' => $stage->name,
        'date' => current_time('mysql'),
        'user' => get_current_user_id()
    ));

    // Limpar cache
    wpkanban_clear_transients();

    wp_send_json_success(array(
        'message' => 'Estágio atualizado com sucesso',
        'lead_id' => $lead_id,
        'stage_id' => $stage_id,
        'stage_name' => $stage->name
    ));
}
add_action('wp_ajax_wpkanban_update_lead_stage', 'wpkanban_update_lead_stage_handler');

// Handler para excluir lead
function wpkanban_delete_lead_handler() {
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    if (!current_user_can('delete_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error(array('message' => 'ID do lead inválido'));
    }

    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        wp_send_json_error(array('message' => 'Lead não encontrado'));
    }

    $result = wp_delete_post($lead_id, true);
    
    if (!$result) {
        wp_send_json_error(array('message' => 'Erro ao excluir lead'));
    }

    wpkanban_clear_transients();

    wp_send_json_success(array(
        'message' => 'Lead excluído com sucesso',
        'lead_id' => $lead_id
    ));
}
add_action('wp_ajax_wpkanban_delete_lead', 'wpkanban_delete_lead_handler');

// Handler para carregar dados do lead
function wpkanban_get_lead_handler() {
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error(array('message' => 'ID do lead inválido'));
    }

    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        wp_send_json_error(array('message' => 'Lead não encontrado'));
    }

    $meta = get_post_meta($lead_id);
    $stages = wp_get_object_terms($lead_id, 'etapas');
    $current_stage = !empty($stages) ? $stages[0] : null;

    wp_send_json_success(array(
        'id' => $lead_id,
        'title' => $lead->post_title,
        'stage' => $current_stage ? array(
            'id' => $current_stage->term_id,
            'name' => $current_stage->name
        ) : null,
        'meta' => array(
            'nome' => isset($meta['nome'][0]) ? $meta['nome'][0] : '',
            'email' => isset($meta['email'][0]) ? $meta['email'][0] : '',
            'whatsapp' => isset($meta['whatsapp'][0]) ? $meta['whatsapp'][0] : '',
            'interesse' => isset($meta['interesse'][0]) ? $meta['interesse'][0] : '',
            'anotacoes' => isset($meta['anotacoes'][0]) ? $meta['anotacoes'][0] : '',
            'status' => isset($meta['status'][0]) ? $meta['status'][0] : 'active'
        )
    ));
}
add_action('wp_ajax_wpkanban_get_lead', 'wpkanban_get_lead_handler');

// Handler para atualizar o board
function wpkanban_refresh_board_handler() {
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    $stage_id = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
    if (!$stage_id) {
        wp_send_json_error(array('message' => 'ID da etapa inválido'));
    }

    // Buscar leads desta etapa
    $leads = get_posts(array(
        'post_type' => 'leads',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'etapas',
                'field' => 'term_id',
                'terms' => $stage_id
            )
        )
    ));

    // Gerar HTML dos cards
    ob_start();
    foreach ($leads as $lead) {
        wpkanban_render_lead_card($lead);
    }
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html' => $html,
        'count' => count($leads)
    ));
}
add_action('wp_ajax_wpkanban_refresh_board', 'wpkanban_refresh_board_handler');

// Atualizar etapa do lead
function wpkanban_ajax_update_lead_stage() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $etapa_id = isset($_POST['etapa_id']) ? intval($_POST['etapa_id']) : 0;
    
    if (!$lead_id || !$etapa_id) {
        wp_send_json_error('Dados inválidos');
    }
    
    $result = wp_set_object_terms($lead_id, $etapa_id, 'etapas');
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success();
}
add_action('wp_ajax_wpkanban_update_lead_stage', 'wpkanban_ajax_update_lead_stage');

// Buscar dados do lead para edição
function wpkanban_ajax_get_lead() {
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
add_action('wp_ajax_wpkanban_get_lead', 'wpkanban_ajax_get_lead');

// Salvar lead
function wpkanban_ajax_save_lead() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error('ID do lead inválido');
    }
    
    $data = array(
        'title' => isset($_POST['title']) ? $_POST['title'] : '',
        'nome' => isset($_POST['nome']) ? $_POST['nome'] : '',
        'email' => isset($_POST['email']) ? $_POST['email'] : '',
        'whatsapp' => isset($_POST['whatsapp']) ? $_POST['whatsapp'] : '',
        'interesse' => isset($_POST['interesse']) ? $_POST['interesse'] : '',
        'status' => isset($_POST['status']) ? $_POST['status'] : '0',
        'anotacoes' => isset($_POST['anotacoes']) ? $_POST['anotacoes'] : '',
        'etapa' => isset($_POST['etapa']) ? $_POST['etapa'] : ''
    );
    
    $result = wpkanban_update_lead($lead_id, $data);
    
    if (!$result) {
        wp_send_json_error('Erro ao atualizar lead');
    }
    
    // Retornar HTML atualizado do card
    ob_start();
    wpkanban_render_lead_card(get_post($lead_id));
    $card_html = ob_get_clean();
    
    wp_send_json_success(array(
        'message' => 'Lead atualizado com sucesso',
        'card_html' => $card_html
    ));
}
add_action('wp_ajax_wpkanban_save_lead', 'wpkanban_ajax_save_lead');

// Excluir lead
function wpkanban_ajax_delete_lead() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('delete_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error(array('message' => 'ID do lead inválido'));
    }
    
    $result = wpkanban_delete_lead($lead_id);
    
    if (!$result) {
        wp_send_json_error(array('message' => 'Erro ao excluir lead'));
    }
    
    wp_send_json_success(array('message' => 'Lead excluído com sucesso'));
}
add_action('wp_ajax_wpkanban_ajax_delete_lead', 'wpkanban_ajax_delete_lead');

// Atualizar lista Aguardando
function wpkanban_ajax_refresh_waiting() {
    check_ajax_referer('wpkanban_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissão negada');
    }
    
    // Buscar termo "Aguardando"
    $aguardando = get_term_by('name', 'Aguardando', 'etapas');
    if (!$aguardando) {
        wp_send_json_error('Etapa Aguardando não encontrada');
    }
    
    // Buscar leads
    $leads = get_posts(array(
        'post_type' => 'leads',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'etapas',
                'field' => 'term_id',
                'terms' => $aguardando->term_id
            )
        )
    ));
    
    // Gerar HTML dos cards
    ob_start();
    foreach ($leads as $lead) {
        wpkanban_render_lead_card($lead);
    }
    $cards_html = ob_get_clean();
    
    wp_send_json_success(array(
        'html' => $cards_html,
        'count' => count($leads)
    ));
}
add_action('wp_ajax_wpkanban_refresh_waiting', 'wpkanban_ajax_refresh_waiting');

// Carregar mais leads
function wpkanban_load_more_leads_handler() {
    if (!check_ajax_referer('wpkanban_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permissão negada'));
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $stage_id = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
    $per_page = 20;

    $args = array(
        'post_type' => 'leads',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    );

    if ($stage_id) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'etapas',
                'field' => 'term_id',
                'terms' => $stage_id
            )
        );
    }

    $query = new WP_Query($args);
    $leads = $query->posts;

    $html = '';
    if (!empty($leads)) {
        ob_start();
        foreach ($leads as $lead) {
            wpkanban_render_lead_card($lead);
        }
        $html = ob_get_clean();
    }

    wp_send_json_success(array(
        'html' => $html,
        'has_more' => $query->max_num_pages > $page,
        'total' => $query->found_posts
    ));
}
add_action('wp_ajax_wpkanban_load_more_leads', 'wpkanban_load_more_leads_handler');

// Handler para obter listas atualizadas
function wpkanban_get_updated_lists_handler() {
    // Verifica o nonce para segurança
    check_ajax_referer('wpkanban_nonce', 'nonce');

    // Aqui você deve implementar a lógica para buscar os dados atualizados das listas
    // Por exemplo, buscar posts ou termos relacionados e formatar em HTML
    $response = array(
        'success' => true,
        'data' => array(
            'html' => '<div>Dados atualizados das listas</div>' // Substitua pelo HTML real
        )
    );

    wp_send_json($response);
}
add_action('wp_ajax_wpkanban_get_updated_lists', 'wpkanban_get_updated_lists_handler');
add_action('wp_ajax_nopriv_wpkanban_get_updated_lists', 'wpkanban_get_updated_lists_handler');
