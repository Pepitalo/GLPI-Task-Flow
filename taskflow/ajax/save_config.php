<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

global $DB;

header('Content-Type: application/json');

$forms_id     = (int) ($_POST['forms_id'] ?? 0);
$questions_id = (int) ($_POST['questions_id'] ?? 0);
$mappings     = $_POST['mappings'] ?? [];

if (!$forms_id || !$questions_id) {
    echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants']);
    exit;
}

$config = $DB->request([
    'FROM'  => 'glpi_plugin_taskflow_forms',
    'WHERE' => ['plugin_formcreator_questions_id' => $questions_id],
])->current();

if ($config) {
    $config_id = $config['id'];
    $DB->update('glpi_plugin_taskflow_forms', [
        'plugin_formcreator_forms_id' => $forms_id,
        'date_mod' => date('Y-m-d H:i:s'),
    ], ['id' => $config_id]);
} else {
    $formcreator_form = $DB->request([
        'FROM'  => 'glpi_plugin_formcreator_forms',
        'WHERE' => ['id' => $forms_id]
    ])->current();
    $form_name = $formcreator_form['name'] ?? 'Formulaire #' . $forms_id;

    $DB->insert('glpi_plugin_taskflow_forms', [
        'name'                            => $form_name,
        'plugin_formcreator_forms_id'     => $forms_id,
        'plugin_formcreator_questions_id' => $questions_id,
        'date_creation'                   => date('Y-m-d H:i:s'),
    ]);
    $config_id = $DB->insertId();
}

foreach ($mappings as $mapping) {
    $value   = $mapping['value'] ?? '';
    $message = trim($mapping['message'] ?? '');

    if ($value === '') {
        continue;
    }

    $existing = $DB->request([
        'FROM'  => 'glpi_plugin_taskflow_checkboxes',
        'WHERE' => [
            'plugin_taskflow_forms_id' => $config_id,
            'value'                         => $value,
        ],
    ])->current();

    if ($message === '') {
        if ($existing) {
            $DB->delete('glpi_plugin_taskflow_checkboxes', ['id' => $existing['id']]);
        }
        continue;
    }

    if ($existing) {
        $DB->update('glpi_plugin_taskflow_checkboxes', [
            'message'  => $message,
            'date_mod' => date('Y-m-d H:i:s'),
        ], ['id' => $existing['id']]);
    } else {
        $DB->insert('glpi_plugin_taskflow_checkboxes', [
            'plugin_taskflow_forms_id' => $config_id,
            'value'                         => $value,
            'message'                       => $message,
            'date_creation'                 => date('Y-m-d H:i:s'),
        ]);
    }
}

echo json_encode(['status' => 'ok']);