<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

global $DB;

$forms_id = (int) ($_GET['forms_id'] ?? 0);

if (!$forms_id) {
    exit;
}

// 1. Toutes les questions exploitables du formulaire
$questions = $DB->request([
    'SELECT'     => ['q.id', 'q.name', 'q.fieldtype', 'q.values', 'q.row'],
    'FROM'       => 'glpi_plugin_formcreator_questions AS q',
    'INNER JOIN' => [
        'glpi_plugin_formcreator_sections AS s' => [
            'ON' => ['s' => 'id', 'q' => 'plugin_formcreator_sections_id']
        ]
    ],
    'WHERE' => [
        's.plugin_formcreator_forms_id' => $forms_id,
        'q.fieldtype' => ['radios', 'select', 'checkboxes'],
    ],
    'ORDER' => 'q.row',
]);

$questions_by_id = [];
foreach ($questions as $q) {
    $questions_by_id[$q['id']] = $q;
}

// 2. Configs existantes (accepte les doublons)
$existing_configs = iterator_to_array($DB->request([
    'FROM'  => 'glpi_plugin_taskflow_forms',
    'WHERE' => ['plugin_formcreator_forms_id' => $forms_id],
]), false);

$config_ids = array_column($existing_configs, 'id');
$existing_messages = [];

if (!empty($config_ids)) {
    $checkboxes = $DB->request([
        'FROM'  => 'glpi_plugin_taskflow_checkboxes',
        'WHERE' => ['plugin_taskflow_forms_id' => $config_ids],
    ]);
    foreach ($checkboxes as $cb) {
        $existing_messages[$cb['plugin_taskflow_forms_id']][$cb['value']] = $cb['message'];
    }
}

// 3. Blocs "toujours envoyés"
$always_blocks = iterator_to_array($DB->request([
    'FROM'  => 'glpi_plugin_taskflow_always_blocks',
    'WHERE' => ['plugin_formcreator_forms_id' => $forms_id],
]), false);

// 4. Organisation des blocs pour l'affichage
$positioned = [];
$configured_question_ids = [];

// Ajout des questions déjà configurées (peut inclure des doublons)
foreach ($existing_configs as $config) {
    $q_id = $config['plugin_formcreator_questions_id'];
    if (isset($questions_by_id[$q_id])) {
        $positioned[] = [
            'ordre' => (int) $config['ordre'],
            'item'  => ['type' => 'question', 'question' => $questions_by_id[$q_id], 'config' => $config]
        ];
        $configured_question_ids[] = $q_id;
    }
}

// Ajout des blocs "toujours envoyés"
foreach ($always_blocks as $block) {
    $positioned[] = [
        'ordre' => (int) $block['ordre'],
        'item'  => ['type' => 'always', 'block' => $block]
    ];
}

usort($positioned, function ($a, $b) {
    return $a['ordre'] <=> $b['ordre'];
});

$ordered_items = array_column($positioned, 'item');

// On ajoute à la fin les questions qui n'ont jamais été configurées
foreach ($questions_by_id as $q_id => $q) {
    if (!in_array($q_id, $configured_question_ids)) {
        $ordered_items[] = [
            'type' => 'question',
            'question' => $q,
            'config' => null
        ];
    }
}

if (empty($ordered_items)) {
    echo '<p class="text-muted">' . __("Aucune question paramétrable (radio/select/case à cocher) sur ce formulaire.", 'taskflow') . '</p>';
    exit;
}

// 5. Rendu HTML (Interface épurée)
foreach ($ordered_items as $item) {
    if ($item['type'] === 'always') {
        $block = $item['block'];
        echo '<div class="card mb-3 shadow-sm taskflow-block" data-type="always">';
        echo '<div class="card-header bg-light d-flex justify-content-between align-items-center py-2">';
        echo '<strong class="text-secondary">► ' . __('Bloc toujours envoyé', 'taskflow') . '</strong>';
        echo '<div class="taskflow-block-actions">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-up" title="Monter">&uarr;</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-down" title="Descendre">&darr;</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-danger taskflow-remove-block" title="Supprimer">✖</button>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body py-2">';
        echo '<textarea class="form-control taskflow-always-message" rows="1" placeholder="Message à ajouter...">' . htmlspecialchars($block['message']) . '</textarea>';
        echo '</div>';
        echo '</div>';
        continue;
    }

    // type === 'question'
    $question = $item['question'];
    $config   = $item['config'];
    $values   = json_decode($question['values'], true);
    if (!is_array($values)) {
        $values = [];
    }
    $messages = $config ? ($existing_messages[$config['id']] ?? []) : [];

    echo '<div class="card mb-3 shadow-sm taskflow-block" data-type="question" data-questions-id="' . (int) $question['id'] . '">';
    echo '<div class="card-header bg-light d-flex justify-content-between align-items-center py-2">';
    echo '<strong class="text-primary">▾ ' . htmlspecialchars($question['name']) . '</strong>';
    echo '<div class="taskflow-block-actions">';
    echo '<button type="button" class="btn btn-sm btn-outline-primary taskflow-duplicate-block" title="Dupliquer la question">Dupliquer</button> ';
    echo '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-up" title="Monter">&uarr;</button> ';
    echo '<button type="button" class="btn btn-sm btn-outline-secondary taskflow-move-down" title="Descendre">&darr;</button> ';
    echo '<button type="button" class="btn btn-sm btn-outline-danger taskflow-remove-block" title="Retirer de la configuration">✖</button>';
    echo '</div>';
    echo '</div>';

    echo '<div class="card-body py-2">';
    if (empty($values)) {
        echo '<p class="text-muted mb-0">' . __("Cette question n'a pas de valeurs fixes exploitables.", 'taskflow') . '</p>';
    }

    foreach ($values as $value) {
        $message = $messages[$value] ?? '';
        echo '<div class="mb-2 taskflow-row d-flex align-items-center" data-value="' . htmlspecialchars($value, ENT_QUOTES) . '">';
        echo '<label class="me-2 text-nowrap w-25 text-end pe-2 mb-0"><strong>' . htmlspecialchars($value) . '</strong></label>';
        echo '<textarea class="form-control taskflow-message flex-grow-1" rows="1" placeholder="Si cochée, ajouter le message...">' . htmlspecialchars($message) . '</textarea>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
