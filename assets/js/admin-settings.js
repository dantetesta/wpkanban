jQuery(document).ready(function($) {
    // Inicializa o Sortable nas etapas
    if ($('#wpkanban-etapas-sort').length) {
        new Sortable(document.getElementById('wpkanban-etapas-sort'), {
            animation: 150,
            ghostClass: 'wpkanban-sortable-ghost',
            onEnd: function() {
                // Coleta a nova ordem
                var ordem = [];
                $('#wpkanban-etapas-sort .wpkanban-etapa-item').each(function(index) {
                    ordem.push({
                        term_id: $(this).data('term-id'),
                        order: index
                    });
                });

                // Salva a nova ordem via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpkanban_save_etapas_order',
                        ordem: ordem,
                        nonce: wpkanbanSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Mostra mensagem de sucesso
                            var notice = $('<div class="notice notice-success is-dismissible"><p>Ordem das etapas atualizada com sucesso!</p></div>');
                            $('.wrap h1').after(notice);
                            setTimeout(function() {
                                notice.fadeOut(function() {
                                    notice.remove();
                                });
                            }, 3000);
                        }
                    }
                });
            }
        });
    }
});
