<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header('TaskFlow - Configuration', $_SERVER['PHP_SELF'], 'admin', 'PluginTaskflowConfig');

global $DB;

$forms = $DB->request([
    'FROM'  => 'glpi_plugin_formcreator_forms',
    'WHERE' => ['is_deleted' => 0],
    'ORDER' => 'name',
]);
?>

<div class="card">
    <div class="card-body">
        <label for="taskflow_form_select">Formulaire Formcreator</label>
        <select id="taskflow_form_select" class="form-select">
            <option value="">-- Sélectionner un formulaire --</option>
            <?php foreach ($forms as $form): ?>
                <option value="<?= (int)$form['id'] ?>"><?= htmlspecialchars($form['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div id="taskflow_blocks_zone" class="card mt-3" style="display:none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <?= __('Blocs de tâches pour ce formulaire', 'taskflow') ?>
            <small class="d-block text-muted">
                <?= __("L'ordre affiché de haut en bas correspond à l'ordre d'ajout des tâches au ticket. Les blocs laissés vides ne seront pas sauvegardés.", 'taskflow') ?>
            </small>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" id="taskflow_add_always_btn"><?= __('+ Ajouter un bloc toujours envoyé', 'taskflow') ?></button>
    </div>
    <div class="card-body" id="taskflow_blocks_content" style="background-color: #f8f9fa;"></div>
    <div class="card-footer">
        <button type="button" class="btn btn-primary" id="taskflow_save_all_btn"><?= __('Enregistrer', 'taskflow') ?></button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var pluginRoot = '<?= Plugin::getWebDir('taskflow') ?>';

    function updateArrowStates() {
        var $blocks = $('#taskflow_blocks_content .taskflow-block');
        $blocks.find('.taskflow-move-up, .taskflow-move-down').prop('disabled', false).show();
        if ($blocks.length) {
            $blocks.first().find('.taskflow-move-up').prop('disabled', true);
            $blocks.last().find('.taskflow-move-down').prop('disabled', true);
        }
    }

    // Adapté au nouveau style visuel plus épuré
    function newAlwaysBlockHtml() {
        return '<div class="card mb-3 shadow-sm taskflow-block" data-type="always">' +
            '<div class="card-header bg-light d-flex justify-content-between align-items-center py-2">' +
                '<strong class="text-secondary">► Bloc toujours envoyé</strong>' +
                '<div class="taskflow-block-actions">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-up">&uarr;</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-down">&darr;</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-danger taskflow-remove-block">✖</button>' +
                '</div>' +
            '</div>' +
            '<div class="card-body py-2">' +
                '<textarea class="form-control taskflow-always-message" rows="1" placeholder="Message à ajouter..."></textarea>' +
            '</div>' +
        '</div>';
    }

    $('#taskflow_form_select').on('change', function() {
        var forms_id = $(this).val();
        if (!forms_id) {
            $('#taskflow_blocks_zone').hide();
            return;
        }
        $.get(pluginRoot + '/ajax/form_blocks.php', { forms_id: forms_id }, function(data) {
            $('#taskflow_blocks_content').html(data);
            $('#taskflow_blocks_zone').show();
            updateArrowStates();
        }).fail(function(xhr) {
            console.error("Erreur AJAX blocs : ", xhr.responseText);
        });
    });

    $('#taskflow_add_always_btn').on('click', function() {
        $('#taskflow_blocks_content').append(newAlwaysBlockHtml());
        updateArrowStates();
    });

    $(document).on('click', '.taskflow-remove-block', function() {
        $(this).closest('.taskflow-block').remove();
        updateArrowStates();
    });

    // NOUVEAU : Logique de duplication
    $(document).on('click', '.taskflow-duplicate-block', function() {
        var $block = $(this).closest('.taskflow-block');
        var $clone = $block.clone();

        // jQuery clone() ne copie pas le contenu dynamique des textarea
        $block.find('textarea').each(function(index) {
            $clone.find('textarea').eq(index).val($(this).val());
        });

        $clone.insertAfter($block);
        updateArrowStates();
    });

    $(document).on('click', '.taskflow-move-up', function() {
        var $block = $(this).closest('.taskflow-block');
        var $prev = $block.prev('.taskflow-block');
        if ($prev.length) {
            $block.insertBefore($prev);
            updateArrowStates();
        }
    });

    $(document).on('click', '.taskflow-move-down', function() {
        var $block = $(this).closest('.taskflow-block');
        var $next = $block.next('.taskflow-block');
        if ($next.length) {
            $block.insertAfter($next);
            updateArrowStates();
        }
    });

    $('#taskflow_save_all_btn').on('click', function() {
        var forms_id = $('#taskflow_form_select').val();
        if (!forms_id) {
            alert('Sélectionnez d\'abord un formulaire.');
            return;
        }

        var blocks = [];
        $('#taskflow_blocks_content .taskflow-block').each(function() {
            var $block = $(this);
            if ($block.data('type') === 'always') {
                blocks.push({
                    type: 'always',
                    message: $block.find('.taskflow-always-message').val()
                });
            } else {
                var mappings = [];
                $block.find('.taskflow-row').each(function() {
                    mappings.push({
                        value:   $(this).data('value'),
                        message: $(this).find('.taskflow-message').val()
                    });
                });
                blocks.push({
                    type: 'question',
                    questions_id: $block.data('questions-id'),
                    mappings: mappings
                });
            }
        });

        $.ajax({
            url: pluginRoot + '/ajax/save_all.php?forms_id=' + encodeURIComponent(forms_id),
            type: 'POST',
            contentType: 'application/json; charset=utf-8',
            data: JSON.stringify({ blocks: blocks }),
            dataType: 'json'
        }).done(function(response) {
            if (response && response.status === 'ok') {
                alert('Configuration enregistrée.');
                // Recharge pour afficher l'état propre après sauvegarde.
                $('#taskflow_form_select').trigger('change');
            } else {
                alert('Erreur : ' + (response && response.message ? response.message : 'inconnue'));
            }
        }).fail(function(xhr) {
            console.error("Erreur AJAX sauvegarde : ", xhr.responseText);
            alert("Une erreur est survenue lors de la sauvegarde (voir la console).");
        });
    });
});
</script>

<?php
Html::footer();
