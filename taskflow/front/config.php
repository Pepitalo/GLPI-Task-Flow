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

<div id="taskflow_questions_zone" class="card mt-3" style="display:none;">
    <div class="card-body">
        <label for="taskflow_question_select">Question à paramétrer</label>
        <select id="taskflow_question_select" class="form-select"></select>
    </div>
</div>

<div id="taskflow_values_zone" class="card mt-3" style="display:none;">
    <div class="card-body" id="taskflow_values_content"></div>
    <div class="card-footer">
        <button type="button" class="btn btn-primary" id="taskflow_save_btn">Enregistrer</button>
    </div>
</div>

<script>
// CORRECTION : On sécurise l'usage du $ pour GLPI 10
jQuery(document).ready(function($) {
    // CORRECTION : URL complète pour éviter les problèmes de sous-dossiers
    var pluginRoot = '<?= Plugin::getWebDir('taskflow') ?>'; 

    $('#taskflow_form_select').on('change', function() {
        var forms_id = $(this).val();
        $('#taskflow_values_zone').hide();
        if (!forms_id) {
            $('#taskflow_questions_zone').hide();
            return;
        }
        $.get(pluginRoot + '/ajax/formcreator_questions.php', { forms_id: forms_id }, function(data) {
            $('#taskflow_question_select').html(data);
            $('#taskflow_questions_zone').show();
        }).fail(function(xhr) {
            console.error("Erreur AJAX questions : ", xhr.responseText);
        });
    });

    $(document).on('change', '#taskflow_question_select', function() {
        var questions_id = $(this).val();
        var forms_id = $('#taskflow_form_select').val();
        if (!questions_id) {
            $('#taskflow_values_zone').hide();
            return;
        }
        $.get(pluginRoot + '/ajax/question_values.php', { questions_id: questions_id, forms_id: forms_id }, function(data) {
            $('#taskflow_values_content').html(data);
            $('#taskflow_values_zone').show();
        }).fail(function(xhr) {
            console.error("Erreur AJAX valeurs : ", xhr.responseText);
        });
    });

    $('#taskflow_save_btn').on('click', function() {
        var forms_id     = $('#taskflow_form_select').val();
        var questions_id = $('#taskflow_question_select').val();
        var mappings = [];
        $('#taskflow_values_content [data-value]').each(function() {
            mappings.push({
                value:   $(this).data('value'),
                message: $(this).val()
            });
        });

        $.post(pluginRoot + '/ajax/save_config.php', {
            forms_id: forms_id,
            questions_id: questions_id,
            mappings: mappings
        }, function() {
            alert('Configuration enregistrée.');
        }).fail(function(xhr) {
            console.error("Erreur AJAX sauvegarde : ", xhr.responseText);
        });
    });
});
</script>

<?php
Html::footer();