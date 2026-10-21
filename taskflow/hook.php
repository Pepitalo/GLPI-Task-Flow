<?php

/**
 * Renomme les tables et la clé étrangère issues de l'ancien plugin
 * "eremstaskflow" vers les nouveaux noms "taskflow" (installation existante).
 * Les données sont conservées.
 */
function plugin_taskflow_migrateFromEremstaskflow() {
    global $DB;

    $renames = [
        'glpi_plugin_eremstaskflow_forms'         => 'glpi_plugin_taskflow_forms',
        'glpi_plugin_eremstaskflow_checkboxes'    => 'glpi_plugin_taskflow_checkboxes',
        'glpi_plugin_eremstaskflow_always_blocks' => 'glpi_plugin_taskflow_always_blocks',
    ];

    foreach ($renames as $old => $new) {
        if ($DB->tableExists($old) && !$DB->tableExists($new)) {
            $DB->query("RENAME TABLE `$old` TO `$new`") or die($DB->error());
        }
    }

    // Clé étrangère : plugin_eremstaskflow_forms_id -> plugin_taskflow_forms_id
    if ($DB->tableExists('glpi_plugin_taskflow_checkboxes')
        && $DB->fieldExists('glpi_plugin_taskflow_checkboxes', 'plugin_eremstaskflow_forms_id')
        && !$DB->fieldExists('glpi_plugin_taskflow_checkboxes', 'plugin_taskflow_forms_id')) {
        $DB->query("ALTER TABLE `glpi_plugin_taskflow_checkboxes`
            DROP KEY `plugin_eremstaskflow_forms_id`,
            CHANGE `plugin_eremstaskflow_forms_id` `plugin_taskflow_forms_id` int(11) unsigned NOT NULL DEFAULT 0,
            ADD KEY `plugin_taskflow_forms_id` (`plugin_taskflow_forms_id`)"
        ) or die($DB->error());
    }
}

function plugin_taskflow_install() {
    global $DB;

    // Reprise éventuelle d'une installation de l'ancien plugin "eremstaskflow"
    plugin_taskflow_migrateFromEremstaskflow();

    if (!$DB->tableExists('glpi_plugin_taskflow_forms')) {
        $query = "CREATE TABLE `glpi_plugin_taskflow_forms` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `plugin_formcreator_forms_id` int(11) unsigned NOT NULL DEFAULT 0,
            `plugin_formcreator_questions_id` int(11) unsigned NOT NULL DEFAULT 0,
            `ordre` int(11) NOT NULL DEFAULT 0,
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`),
            KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->query($query) or die($DB->error());
    } else {
        if (!$DB->fieldExists('glpi_plugin_taskflow_forms', 'plugin_formcreator_forms_id')) {
            // Migration pour une installation existante (avant l'ajout du niveau "formulaire")
            $DB->query("ALTER TABLE `glpi_plugin_taskflow_forms`
                ADD COLUMN `plugin_formcreator_forms_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `name`,
                ADD KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`)"
            ) or die($DB->error());
        }
        if (!$DB->fieldExists('glpi_plugin_taskflow_forms', 'ordre')) {
            // Migration : l'ordre est désormais géré au niveau du bloc/question,
            // plus au niveau de chaque valeur de case à cocher.
            $DB->query("ALTER TABLE `glpi_plugin_taskflow_forms`
                ADD COLUMN `ordre` int(11) NOT NULL DEFAULT 0 AFTER `plugin_formcreator_questions_id`"
            ) or die($DB->error());
        }
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
    } else {
        // Migration : l'ordre par valeur est abandonné au profit de l'ordre par bloc.
        if ($DB->fieldExists('glpi_plugin_taskflow_checkboxes', 'ordre')) {
            $DB->query("ALTER TABLE `glpi_plugin_taskflow_checkboxes` DROP COLUMN `ordre`")
                or die($DB->error());
        }
        // Ancien concept "toujours envoyé" au niveau de la case (obsolète depuis
        // l'introduction de glpi_plugin_taskflow_always_blocks).
        if ($DB->fieldExists('glpi_plugin_taskflow_checkboxes', 'always_send')) {
            $DB->query("ALTER TABLE `glpi_plugin_taskflow_checkboxes` DROP COLUMN `always_send`")
                or die($DB->error());
        }
    }

    if (!$DB->tableExists('glpi_plugin_taskflow_always_blocks')) {
        $query = "CREATE TABLE `glpi_plugin_taskflow_always_blocks` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `plugin_formcreator_forms_id` int(11) unsigned NOT NULL DEFAULT 0,
            `message` text,
            `ordre` int(11) NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->query($query) or die($DB->error());
    }

    return true;
}

function plugin_taskflow_uninstall() {
    global $DB;
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_taskflow_always_blocks`");
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

    // On récupère le formulaire Formcreator réellement soumis, pour ne traiter
    // que les configurations qui lui appartiennent (et pas celles d'autres
    // formulaires qui partageraient par coïncidence un même identifiant de
    // question ou de valeur).
    $formanswer_data = $DB->request([
        'FROM'  => 'glpi_plugin_formcreator_formanswers',
        'WHERE' => ['id' => $formanswer_id],
    ])->current();
    $plugin_formcreator_forms_id = $formanswer_data['plugin_formcreator_forms_id'] ?? 0;

    // On accumule toutes les tâches à créer (message + ordre) avant de les
    // envoyer, afin de pouvoir les trier globalement par ordre croissant.
    $tasks_to_create = [];

    // 1) Blocs "toujours envoyés" : rattachés au formulaire, indépendants de
    // toute question ou valeur de réponse.
    $always_blocks = $DB->request([
        'FROM'  => 'glpi_plugin_taskflow_always_blocks',
        'WHERE' => ['plugin_formcreator_forms_id' => $plugin_formcreator_forms_id],
    ]);
    foreach ($always_blocks as $block) {
        $tasks_to_create[] = [
            'ordre'   => (int) $block['ordre'],
            'message' => $block['message'],
        ];
    }

    // 2) Cases conditionnelles : uniquement les configs appartenant à ce formulaire.
    $configs = $DB->request([
        'FROM'  => 'glpi_plugin_taskflow_forms',
        'WHERE' => ['plugin_formcreator_forms_id' => $plugin_formcreator_forms_id],
    ]);

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

            $tasks_to_create[] = [
                'ordre'   => (int) $config['ordre'],
                'message' => $mapping['message'],
            ];
        }
    }

    // Tri global par ordre croissant (stable) avant création effective des tâches.
    usort($tasks_to_create, function ($a, $b) {
        return $a['ordre'] <=> $b['ordre'];
    });

    foreach ($tasks_to_create as $task_data) {
        $task = new TicketTask();
        $task->add([
            'tickets_id'        => $ticket_id,
            'content'           => addslashes($task_data['message']),
            'is_private'        => 0,
            'actiontime'        => 0,
            'state'             => Planning::TODO,
            'taskcategories_id' => 0,
        ]);
    }
}
