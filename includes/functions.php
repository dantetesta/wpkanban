<?php
if (!defined('ABSPATH')) {
    exit;
}

// Renderizar card do lead
function wpkanban_render_lead_card($lead) {
    $meta = get_post_meta($lead->ID);
    $nome = isset($meta['nome'][0]) ? esc_html($meta['nome'][0]) : '';
    $email = isset($meta['email'][0]) ? esc_html($meta['email'][0]) : '';
    $whatsapp = isset($meta['whatsapp'][0]) ? esc_html($meta['whatsapp'][0]) : '';
    $interesse = isset($meta['interesse'][0]) ? esc_html($meta['interesse'][0]) : '';
    $anotacoes = isset($meta['anotacoes'][0]) ? wp_kses_post($meta['anotacoes'][0]) : '';
    $status = isset($meta['status'][0]) ? esc_html($meta['status'][0]) : 'active';
    
    // Verifica se tem anotações
    $has_notes = !empty(trim(strip_tags($anotacoes)));
    ?>
    <div class="lead-card" data-lead-id="<?php echo esc_attr($lead->ID); ?>">
        <div class="lead-header">
            <h3><?php echo $nome ?: 'Lead sem nome'; ?></h3>
            <div class="lead-actions">
                <button type="button" class="edit-lead" title="Editar lead">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="delete-lead" title="Excluir lead">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        
        <div class="lead-body">
            <?php if ($email) : ?>
                <p>
                    <span class="dashicons dashicons-email"></span>
                    <a href="mailto:<?php echo $email; ?>" title="Enviar email"><?php echo $email; ?></a>
                </p>
            <?php endif; ?>
            
            <?php if ($whatsapp) : ?>
                <p>
                    <span class="dashicons dashicons-whatsapp"></span>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" target="_blank" title="Abrir WhatsApp">
                        <?php echo $whatsapp; ?>
                    </a>
                </p>
            <?php endif; ?>
            
            <?php if ($interesse) : ?>
                <p>
                    <span class="dashicons dashicons-admin-home"></span>
                    <strong>Interesse:</strong> 
                    <span><?php echo $interesse; ?></span>
                </p>
            <?php endif; ?>

            <?php if ($has_notes) : ?>
                <div class="lead-notes-wrapper">
                    <button type="button" class="notes-toggle">
                        <span class="dashicons dashicons-editor-ul"></span>
                        <span class="toggle-text">Ver anotações</span>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="lead-notes" style="display:none">
                        <?php echo wpautop($anotacoes); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="lead-footer">
                <?php 
                $data_criacao = get_the_date('d/m/Y', $lead->ID);
                $hora_criacao = get_the_date('H:i', $lead->ID);
                if ($data_criacao) : ?>
                    <span class="lead-date" title="Data de criação">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo $data_criacao; ?>
                    </span>
                    <span class="lead-time" title="Hora de criação">
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo $hora_criacao; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// Buscar dados do lead para edição
function wpkanban_get_lead_data($lead_id) {
    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        return false;
    }

    $meta = get_post_meta($lead->ID);
    
    return array(
        'id' => $lead->ID,
        'title' => $lead->post_title,
        'nome' => isset($meta['nome'][0]) ? $meta['nome'][0] : '',
        'email' => isset($meta['email'][0]) ? $meta['email'][0] : '',
        'whatsapp' => isset($meta['whatsapp'][0]) ? $meta['whatsapp'][0] : '',
        'interesse' => isset($meta['interesse'][0]) ? $meta['interesse'][0] : '',
        'status' => isset($meta['status'][0]) ? $meta['status'][0] : 'active',
        'anotacoes' => isset($meta['anotacoes'][0]) ? $meta['anotacoes'][0] : '',
        'etapa' => wp_get_post_terms($lead->ID, 'etapas', array('fields' => 'ids'))[0] ?? '',
        'data_criacao' => get_the_date('d/m/Y H:i', $lead->ID)
    );
}

// Atualizar lead
function wpkanban_update_lead($lead_id, $data) {
    // Atualizar post
    $post_data = array(
        'ID' => $lead_id,
        'post_title' => sanitize_text_field($data['nome'])
    );
    
    $updated = wp_update_post($post_data);
    if (is_wp_error($updated)) {
        return false;
    }
    
    // Atualizar meta fields
    update_post_meta($lead_id, 'nome', sanitize_text_field($data['nome']));
    update_post_meta($lead_id, 'email', sanitize_email($data['email']));
    update_post_meta($lead_id, 'whatsapp', sanitize_text_field($data['whatsapp']));
    update_post_meta($lead_id, 'interesse', sanitize_text_field($data['interesse']));
    update_post_meta($lead_id, 'anotacoes', sanitize_textarea_field($data['anotacoes']));
    
    // Atualizar taxonomia se fornecida
    if (!empty($data['etapa'])) {
        wp_set_object_terms($lead_id, intval($data['etapa']), 'etapas');
    }
    
    return true;
}

// Excluir lead
function wpkanban_delete_lead($lead_id) {
    $lead = get_post($lead_id);
    if (!$lead || $lead->post_type !== 'leads') {
        return false;
    }
    
    return wp_delete_post($lead_id, true);
}

// Renderizar modal de edição
function wpkanban_render_edit_modal() {
    ?>
    <div id="edit-lead-modal" class="wpkanban-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Lead</h2>
                <button type="button" class="modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <form id="edit-lead-form">
                <input type="hidden" name="lead_id" id="edit-lead-id">
                
                <div class="modal-body">
                    <div class="form-row">
                        <label for="edit-lead-nome">Nome</label>
                        <input type="text" id="edit-lead-nome" name="nome" required>
                    </div>
                    
                    <div class="form-row form-row-2-col">
                        <div class="form-col">
                            <label for="edit-lead-email">Email</label>
                            <input type="email" id="edit-lead-email" name="email" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="edit-lead-whatsapp">WhatsApp</label>
                            <input type="text" id="edit-lead-whatsapp" name="whatsapp">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label for="edit-lead-interesse">Interesse</label>
                        <?php 
                        $terms = get_terms(array(
                            'taxonomy' => 'interesse',
                            'hide_empty' => false,
                        ));
                        ?>
                        <select id="edit-lead-interesse" name="interesse" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($terms as $term) : ?>
                                <option value="<?php echo esc_attr($term->slug); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="edit-lead-anotacoes">Anotações</label>
                        <?php 
                        wp_editor('', 'edit-lead-anotacoes', array(
                            'textarea_name' => 'anotacoes',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'tinymce'       => array(
                                'toolbar1'  => 'bold,italic,underline,bullist,numlist,link,unlink',
                                'toolbar2'  => '',
                                'toolbar3'  => '',
                            ),
                            'quicktags'     => false,
                        ));
                        ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel modal-close">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
