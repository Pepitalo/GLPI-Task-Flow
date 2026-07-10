<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

global $DB;

$questions_id = (int) ($_GET['questions_id'] ?? 0);

if (!$questions_id) {
    exit;
}

$question = $DB->request([
    'FROM'  => 'glpi_plugin_formcreator_questions',
    'WHERE' => ['id' => $questions_id],
])->current();

if (!$question) {
    echo '<p>Question introuvable.</p>';
    exit;
}

$values = json_decode($question['values'], true);
if (!is_array($values)) {
    echo '<p>Cette question n\'a pas de valeurs fixes exploitables.</p>';
    exit;
}

$config = $DB->request([
    'FROM'  => 'glpi_plugin_taskflow_forms',
    'WHERE' => ['plugin_formcreator_questions_id' => $questions_id],
])->current();

$existing_messages = [];
if ($config) {
    $checkboxes = $DB->request([
        'FROM'  => 'glpi_plugin_taskflow_checkboxes',
        'WHERE' => ['plugin_taskflow_forms_id' => $config['id']],
    ]);
    foreach ($checkboxes as $cb) {
        $existing_messages[$cb['value']] = $cb['message'];
    }
}

foreach ($values as $value) {
    $message = $existing_messages[$value] ?? '';
    echo '<div class="mb-2">';
    echo '<label>' . htmlspecialchars($value) . '</label>';
    echo '<textarea class="form-control" data-value="' . htmlspecialchars($value) . '">' . htmlspecialchars($message) . '</textarea>';
    echo '</div>';
}