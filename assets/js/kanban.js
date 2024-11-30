jQuery(document).ready(function($) {
    'use strict';

    // Cache de elementos DOM
    const $board = $('#wpkanban-board');
    const $columns = $('.wpkanban-column');
    const $modal = $('#edit-lead-modal');
    const $editForm = $('#edit-lead-form');

    // Variáveis globais para controle do timer
    let refreshTimer;
    const refreshInterval = wpkanban.refresh_interval || 20000; // 20 segundos padrão

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
                
                // Reiniciar o timer após mover o card
                restartRefreshTimer();
                
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
                    action: 'wpkanban_ajax_delete_lead',
                    lead_id: leadId,
                    nonce: wpkanban.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remover card com animação
                        $card.fadeOut(200, function() {
                            $(this).remove();
                            updateColumnCounters(); // Atualizar contadores após excluir
                        });
                        showNotification('success', 'Lead excluído com sucesso');
                    } else {
                        $card.removeClass('is-loading');
                        showNotification('error', response.data.message || 'Erro ao excluir lead');
                    }
                },
                error: function() {
                    $card.removeClass('is-loading');
                    showNotification('error', 'Erro ao excluir lead');
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
            const $column = $(this);
            const $counter = $column.find('.column-header .lead-count');
            const count = $column.find('.column-content .lead-card').length;
            $counter.text(count);
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
        const $content = $column.find('.column-content');
        const $existingCards = $content.find('.lead-card');
        
        // Mostrar loading na coluna
        $column.addClass('is-loading');
        
        $.ajax({
            url: wpkanban.ajax_url,
            type: 'POST',
            data: {
                action: 'wpkanban_refresh_board',
                stage_id: stageId,
                nonce: wpkanban.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Criar um container temporário com o novo HTML
                    const $temp = $('<div>').html(response.data.html);
                    const $newCards = $temp.find('.lead-card');
                    
                    // Remover cards que não existem mais com animação
                    $existingCards.each(function() {
                        const leadId = $(this).data('lead-id');
                        if (!$newCards.filter(`[data-lead-id="${leadId}"]`).length) {
                            const $card = $(this);
                            $card.fadeOut(200, function() {
                                $card.remove();
                                updateColumnCounters();
                            });
                        }
                    });
                    
                    // Adicionar novos cards com animação
                    $newCards.each(function() {
                        const leadId = $(this).data('lead-id');
                        if (!$existingCards.filter(`[data-lead-id="${leadId}"]`).length) {
                            const $card = $(this).clone();
                            $card.hide().prependTo($content).fadeIn(200, function() {
                                updateColumnCounters();
                            });
                        }
                    });
                    
                    // Reinicializar Sortable
                    if ($content.hasClass('ui-sortable')) {
                        $content.sortable('destroy');
                    }
                    
                    $content.sortable({
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
                            updateColumnCounters();
                        },
                        receive: function(e, ui) {
                            updateColumnCounters();
                        },
                        update: function(e, ui) {
                            if (this === ui.item.parent()[0]) {
                                const $column = $(ui.item).closest('.wpkanban-column');
                                const leadId = ui.item.data('lead-id');
                                const stageId = $column.data('stage-id');
                                
                                // Atualizar contadores imediatamente
                                updateColumnCounters();
                                
                                // Reiniciar o timer após mover o card
                                restartRefreshTimer();
                                
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
                                            showNotification('success', wpkanban.strings.lead_moved);
                                            updateColumnCounters();
                                        } else {
                                            if (ui.sender) {
                                                $(ui.sender).sortable('cancel');
                                            }
                                            showNotification('error', response.data.message || wpkanban.strings.error);
                                            updateColumnCounters();
                                        }
                                    },
                                    error: function() {
                                        if (ui.sender) {
                                            $(ui.sender).sortable('cancel');
                                        }
                                        showNotification('error', wpkanban.strings.error);
                                        updateColumnCounters();
                                    }
                                });
                            }
                        }
                    }).disableSelection();
                }
            },
            complete: function() {
                $column.removeClass('is-loading');
                updateColumnCounters();
            }
        });
    }

    // Função para atualizar todas as colunas
    function refreshAllColumns() {
        $('.wpkanban-column').each(function() {
            refreshColumn($(this));
        });
        restartRefreshTimer();
    }

    // Função para reiniciar o timer
    function restartRefreshTimer() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshAllColumns, refreshInterval);
    }

    // Iniciar o timer quando o documento carregar
    $(document).ready(function() {
        restartRefreshTimer();
    });

    // Adicionar auto-reload das listas
    var reloadInterval = wpkanbanSettings.intervalo * 1000; // Converte para milissegundos

    function reloadLists() {
        console.log('Iniciando requisição AJAX para atualizar listas...');
        $.ajax({
            url: wpkanbanSettings.ajax_url, // Verifique se esta URL está correta
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpkanban_get_updated_lists', // Certifique-se de que esta ação está registrada no servidor
                nonce: wpkanbanSettings.nonce // Nonce para segurança
            },
            success: function(response) {
                if (response.success) {
                    // Atualiza o DOM com os dados recebidos
                    $('#kanban-board').html(response.data.html);
                    console.log('Listas atualizadas com sucesso!', response);
                } else {
                    console.error('Erro ao atualizar as listas:', response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Erro na requisição AJAX:', textStatus, errorThrown);
            }
        });
    }

    // Configura o auto-reload das listas
    setInterval(reloadLists, reloadInterval);

    // Função para atualizar uma lista específica
    function reloadList(termId) {
        console.log('Atualizando lista para o termo:', termId);
        $.ajax({
            url: wpkanbanSettings.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpkanban_get_updated_list',
                term_id: termId, // Passa o ID do termo para o servidor
                nonce: wpkanbanSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Atualiza o DOM da lista específica
                    $('#list-' + termId).html(response.data.html);
                    console.log('Lista atualizada com sucesso para o termo:', termId);
                } else {
                    console.error('Erro ao atualizar a lista para o termo:', termId, response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Erro na requisição AJAX para o termo:', termId, textStatus, errorThrown);
            }
        });
    }

    // Inicializa o auto-reload para cada lista
    $('.kanban-list').each(function() {
        var termId = $(this).data('term-id'); // Supondo que cada lista tenha um data attribute com o ID do termo
        setInterval(function() {
            reloadList(termId);
        }, 20000); // Atualiza a cada 20 segundos
    });

    // Inicialização
    updateColumnCounters();
});
