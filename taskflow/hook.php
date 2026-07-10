<?php

function plugin_taskflow_install() {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_taskflow_forms')) {
        $query = "CREATE TABLE `glpi_plugin_taskflow_forms` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `plugin_formcreator_forms_id` int(11) unsigned NOT NULL DEFAULT 0,
            `plugin_formcreator_questions_id` int(11) unsigned NOT NULL DEFAULT 0,
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`),
            KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->query($query) or die($DB->error());
    } else if (!$DB->fieldExists('glpi_plugin_taskflow_forms', 'plugin_formcreator_forms_id')) {
        // Migration pour une installation existante (avant l'ajout du niveau "formulaire")
        $DB->query("ALTER TABLE `glpi_plugin_taskflow_forms`
            ADD COLUMN `plugin_formcreator_forms_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `name`,
            ADD KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`)"
        ) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_taskflow_checkboxes')) {
        $query = "CREATE TABLE `glpi_plugin_taskflow_checkboxes` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `plugin_taskflow_forms_id` int(11) unsigned NOT NULL DEFAULT 0,
            `value` varchar(255) DEFAULT NULL,
            `message` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_taskflow_forms_id` (`plugin_taskflow_forms_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->query($query) or die($DB->error());
    }

    return true;
}

function plugin_taskflow_uninstall() {
    global $DB;
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_taskflow_checkboxes`");
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_taskflow_forms`");
    return true;
}

/**
 * Extrait les valeurs à comparer à partir de la réponse brute d'une question,
 * en fonction de son type de champ Formcreator.
 *
 * @param string|null $fieldtype Le type de champ (colonne fieldtype de glpi_plugin_formcreator_questions)
 * @param string      $raw_value La valeur brute stockée dans glpi_plugin_formcreator_answers.answer
 * @return array Liste de valeurs (toujours un tableau, même pour une réponse unique)
 */
function plugin_taskflow_extractAnswerValues(?string $fieldtype, string $raw_value): array {
    switch ($fieldtype) {
        case 'checkboxes':
        case 'multiselect':
            // Array encodé en json de valeurs
            $values = json_decode($raw_value, true);
            if (!is_array($values)) {
                $values = [$raw_value];
            }
            return $values;

        case 'radios':
        case 'select':
        case 'text':
        case 'textarea':
        case 'date':
        case 'datetime':
        case 'time':
            // Valeur unique de type chaîne
            return [$raw_value];

        default:
            // Type non géré explicitement : comportement générique
            $values = json_decode($raw_value, true);
            if (!is_array($values)) {
                $values = [$raw_value];
            }
            return $values;
    }
}

function plugin_taskflow_formanswer_add($formanswer) {
    global $DB;

    $formanswer_id = $formanswer->getID();

    $link = $DB->request([
        'FROM'  => 'glpi_items_tickets',
        'WHERE' => [
            'itemtype' => 'PluginFormcreatorFormAnswer',
            'items_id' => $formanswer_id,
        ],
    ])->current();

    if (!$link) {
        return;
    }
    $ticket_id = $link['tickets_id'];

    $configs = $DB->request(['FROM' => 'glpi_plugin_taskflow_forms']);

    foreach ($configs as $config) {
        $question_id = $config['plugin_formcreator_questions_id'];

        $question = $DB->request([
            'FROM'  => 'glpi_plugin_formcreator_questions',
            'WHERE' => ['id' => $question_id],
        ])->current();

        $answer = $DB->request([
            'FROM'  => 'glpi_plugin_formcreator_answers',
            'WHERE' => [
                'plugin_formcreator_formanswers_id' => $formanswer_id,
                'plugin_formcreator_questions_id'   => $question_id,
            ],
        ])->current();

        if (!$answer) {
            continue;
        }

        $fieldtype = $question['fieldtype'] ?? null;
        $values = plugin_taskflow_extractAnswerValues($fieldtype, $answer['answer']);

        foreach ($values as $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $mapping = $DB->request([
                'FROM'  => 'glpi_plugin_taskflow_checkboxes',
                'WHERE' => [
                    'plugin_taskflow_forms_id' => $config['id'],
                    'value'                    => $value,
                ],
            ])->current();

            if (!$mapping) {
                continue;
            }

            $task = new TicketTask();
            $task->add([
                'tickets_id'        => $ticket_id,
                'content'           => addslashes($mapping['message']),
                'is_private'        => 0,
                'actiontime'        => 0,
                'state'             => Planning::TODO,
                'taskcategories_id' => 0,
            ]);
        }
    }
}