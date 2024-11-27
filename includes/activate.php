<?php

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Função de ativação do plugin
function wpkanban_activate() {
    // Verifica dependências
    if (!wpkanban_check_dependencies()) {
        deactivate_plugins(plugin_basename(WPKANBAN_FILE));
        wp_die(__('O plugin WPKanban requer o JetEngine ativo para funcionar.', 'wpkanban'));
    }

    // Registra o CPT e taxonomia para poder criar os termos
    wpkanban_register_leads_post_type();
    wpkanban_register_etapas_taxonomy();
    
    // Etapas padrão
    $etapas = array(
        'aguardando' => array(
            'name' => 'Aguardando',
            'order' => 0
        ),
        'em-atendimento' => array(
            'name' => 'Em Atendimento',
            'order' => 1
        ),
        'visita-agendada' => array(
            'name' => 'Visita Agendada',
            'order' => 2
        ),
        'documentacao' => array(
            'name' => 'Documentação',
            'order' => 3
        ),
        'concluido' => array(
            'name' => 'Concluído',
            'order' => 4
        ),
        'desistente' => array(
            'name' => 'Desistente',
            'order' => 5
        )
    );
    
    // Cria as etapas se não existirem
    foreach ($etapas as $slug => $etapa) {
        if (!term_exists($slug, 'etapas')) {
            $term = wp_insert_term($etapa['name'], 'etapas', array(
                'slug' => $slug,
            ));
            
            if (!is_wp_error($term)) {
                update_term_meta($term['term_id'], 'order', $etapa['order']);
            }
        }
    }
    
    // Configurar opções padrão
    add_option('wpkanban_refresh_interval', 20);
    
    // Limpa o cache de rewrite rules
    flush_rewrite_rules();
}
