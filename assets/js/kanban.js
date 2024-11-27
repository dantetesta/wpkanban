jQuery(document).ready(function($) {
    'use strict';

    // Cache de elementos DOM
    const $board = $('#wpkanban-board');
    const $columns = $('.wpkanban-column');
    const $modal = $('#edit-lead-modal');
    const $editForm = $('#edit-lead-form');

    // Inicializar Sortable
    $('.column-content').sortable({
        connectWith: '.column-content',
        placeholder: 'card-placeholder',
        handle: '.lead-header',
        items: '.lead-card',
        helper: 'clone',
        revert: true,
        appendTo: 'body',
        zIndex: 1000,
        start: function(e, ui) {
            ui.placeholder.height(ui.item.height());
            $columns.addClass('is-receiving');
            ui.item.addClass('is-dragging');
        },
        stop: function(e, ui) {
            $columns.removeClass('is-receiving');
            ui.item.removeClass('is-dragging');
        },
        update: function(e, ui) {
            if (this === ui.item.parent()[0]) {
                const $column = $(ui.item).closest('.wpkanban-column');
                const leadId = ui.item.data('lead-id');
                const stageId = $column.data('stage-id');
                
                // Atualizar contadores
                updateColumnCounters();
                
                // Enviar atualização via AJAX
                $.ajax({
                    url: wpkanban.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpkanban_update_lead_stage',
                        lead_id: leadId,
                        stage_id: stageId,
                        nonce: wpkanban.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('success', 'Lead movido com sucesso');
                        } else {
                            if (ui.sender) {
                                $(ui.sender).sortable('cancel');
                            }
                            showNotification('error', response.data.message || 'Erro ao mover lead');
                        }
                    },
                    error: function() {
                        if (ui.sender) {
                            $(ui.sender).sortable('cancel');
                        }
                        showNotification('error', 'Erro ao mover lead');
                    }
                });
            }
        }
    }).disableSelection();

    // Toggle de anotações
    $(document).on('click', '.notes-toggle', function(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        
        var $button = $(this);
        var $notes = $button.parent().find('.lead-notes');
        var $icon = $button.find('.dashicons-arrow-down-alt2');
        
        if ($notes.is(':visible')) {
            $notes.slideUp();
            $button.find('.toggle-text').text('Ver anotações');
            $icon.css('transform', 'rotate(0deg)');
        } else {
            $notes.slideDown();
            $button.find('.toggle-text').text('Ocultar anotações');
            $icon.css('transform', 'rotate(180deg)');
        }
    });

    // Edição de lead
    $(document).on('click', '.edit-lead', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $card = $(this).closest('.lead-card');
        const leadId = $card.data('lead-id');
        
        // Mostrar loading no card
        $card.addClass('is-loading');
        
        // Buscar dados do lead
        $.ajax({
            url: wpkanban.ajax_url,
            type: 'POST',
            data: {
                action: 'wpkanban_get_lead',
                lead_id: leadId,
                nonce: wpkanban.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Preencher formulário
                    $('#edit-lead-id').val(data.id);
                    $('#edit-lead-nome').val(data.meta.nome);
                    $('#edit-lead-email').val(data.meta.email);
                    $('#edit-lead-whatsapp').val(data.meta.whatsapp);
                    $('#edit-lead-interesse').val(data.meta.interesse);
                    
                    // Atualizar conteúdo do editor TinyMCE
                    if (typeof tinymce !== 'undefined') {
                        const editor = tinymce.get('edit-lead-anotacoes');
                        if (editor) {
                            editor.setContent(data.meta.anotacoes || '');
                        }
                    }
                    
                    // Abrir modal
                    $('#edit-lead-modal').addClass('active');
                } else {
                    showNotification('error', response.data.message || 'Erro ao carregar dados do lead');
                }
            },
            error: function() {
                showNotification('error', 'Erro ao carregar dados do lead');
            },
            complete: function() {
                $card.removeClass('is-loading');
            }
        });
    });

    // Deletar lead
    $(document).on('click', '.delete-lead', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $card = $(this).closest('.lead-card');
        const leadId = $card.data('lead-id');
        const leadNome = $card.find('h3').text();
        
        if (confirm(`Tem certeza que deseja excluir o lead "${leadNome}"?`)) {
            // Mostrar loading no card
            $card.addClass('is-loading');
            
            $.ajax({
                url: wpkanban.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpkanban_delete_lead',
                    lead_id: leadId,
                    nonce: wpkanban.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.slideUp(200, function() {
                            $(this).remove();
                            updateColumnCounters();
                        });
                        showNotification('success', 'Lead excluído com sucesso');
                    } else {
                        showNotification('error', response.data.message || 'Erro ao excluir lead');
                    }
                },
                error: function() {
                    showNotification('error', 'Erro ao excluir lead');
                },
                complete: function() {
                    $card.removeClass('is-loading');
                }
            });
        }
    });

    // Fechar modal
    $(document).on('click', '.modal-close', function() {
        $('#edit-lead-modal').removeClass('active');
        
        // Limpar editor TinyMCE
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get('edit-lead-anotacoes');
            if (editor) {
                editor.setContent('');
            }
        }
        
        $('#edit-lead-form')[0].reset();
    });

    // Salvar alterações do lead
    $('#edit-lead-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const formData = new FormData(this);
        
        formData.append('action', 'wpkanban_save_lead');
        formData.append('nonce', wpkanban.nonce);
        
        $btn.prop('disabled', true).text('Salvando...');
        
        $.ajax({
            url: wpkanban.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#edit-lead-modal').removeClass('active');
                    
                    const leadId = $('#edit-lead-id').val();
                    const $card = $('.lead-card[data-lead-id="' + leadId + '"]');
                    $card.replaceWith(response.data.card_html);
                    showNotification('success', response.data.message);
                } else {
                    showNotification('error', response.data.message || 'Erro ao salvar lead');
                }
            },
            error: function() {
                showNotification('error', 'Erro ao salvar lead');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Salvar Alterações');
            }
        });
    });

    // Funções auxiliares
    function updateColumnCounters() {
        $('.wpkanban-column').each(function() {
            const count = $(this).find('.lead-card').length;
            $(this).find('.lead-count').text(count);
        });
    }

    function showNotification(type, message) {
        $('.wpkanban-notification').remove();
        
        const $notification = $('<div>', {
            class: `wpkanban-notification ${type}`,
            text: message
        });

        $('body').append($notification);
        
        setTimeout(() => {
            $notification.addClass('show');
        }, 10);

        setTimeout(() => {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }, 3000);
    }

    // Função para atualizar uma coluna específica
    function refreshColumn($column) {
        const stageId = $column.data('stage-id');
        
        return $.ajax({
            url: wpkanban.ajax_url,
            type: 'POST',
            data: {
                action: 'wpkanban_refresh_board',
                stage_id: stageId,
                nonce: wpkanban.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Atualizar conteúdo da coluna mantendo os cards existentes
                    const $newCards = $(response.data.html);
                    const $existingCards = $column.find('.lead-card');
                    
                    // Adicionar novos cards que não existem
                    $newCards.each(function() {
                        const leadId = $(this).data('lead-id');
                        if (!$existingCards.filter(`[data-lead-id="${leadId}"]`).length) {
                            $(this).hide().prependTo($column.find('.column-content')).slideDown();
                        }
                    });
                    
                    // Remover cards que não existem mais
                    $existingCards.each(function() {
                        const leadId = $(this).data('lead-id');
                        if (!$newCards.filter(`[data-lead-id="${leadId}"]`).length) {
                            $(this).slideUp(function() {
                                $(this).remove();
                            });
                        }
                    });
                    
                    // Atualizar contador
                    updateColumnCounters();
                }
            }
        });
    }
    
    // Função para atualizar todas as colunas
    function refreshAllColumns() {
        $('.wpkanban-column').each(function() {
            refreshColumn($(this));
        });
    }
    
    // Iniciar atualização automática
    const refreshInterval = wpkanban.refresh_interval * 1000; // converter para milissegundos
    if (refreshInterval > 0) {
        setInterval(refreshAllColumns, refreshInterval);
    }

    // Inicialização
    updateColumnCounters();
});
