<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

global $DB;

$forms_id = (int) ($_GET['forms_id'] ?? 0);

echo '<option value="">-- Sélectionner une question --</option>';

if (!$forms_id) {
    exit;
}

$questions = $DB->request([
    'SELECT' => ['q.id', 'q.name'],
    'FROM'   => 'glpi_plugin_formcreator_questions AS q',
    'INNER JOIN' => [
        'glpi_plugin_formcreator_sections AS s' => [
            'FKEY' => [
                's' => 'id',
                'q' => 'plugin_formcreator_sections_id',
            ],
        ],
    ],
    'WHERE' => [
        's.plugin_formcreator_forms_id' => $forms_id,
        'q.fieldtype' => ['radios', 'select', 'checkboxes'],
    ],
    'ORDER' => 'q.row',
]);

foreach ($questions as $question) {
    echo '<option value="' . (int)$question['id'] . '">' . htmlspecialchars($question['name']) . '</option>';
}