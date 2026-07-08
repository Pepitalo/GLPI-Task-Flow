<?php

function plugin_taskflow_install() {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_taskflow_forms')) {
        $query = "CREATE TABLE `glpi_plugin_taskflow_forms` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `plugin_formcreator_questions_id` int(11) unsigned NOT NULL DEFAULT 0,
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->query($query) or die($DB->error());
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

function plugin_taskflow_formanswer_add($formanswer) {
    global $DB;

    $formanswer_id = $formanswer->getID();
    Toolbox::logInFile('taskflow', 'formanswer cree, id = ' . $formanswer_id . "\n");

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

        $answer = $DB->request([
            'FROM'  => 'glpi_plugin_formcreator_answers',
            'WHERE' => [
                'plugin_formcreator_formanswers_id' => $formanswer_id,
                'plugin_formcreator_questions_id'   => $question_id,
            ],
        ])->current();

        if (!$answer) {
            // Capital pour ne pas que le plugin crash
            continue;
        }

        $cases_cochees = json_decode($answer['answer'], true);
        if (!is_array($cases_cochees)) {
            $cases_cochees = [$answer['answer']];
        }

        foreach ($cases_cochees as $case) {
            if ($case === '' || $case === null) {
                continue;
            }

            $mapping = $DB->request([
                'FROM'  => 'glpi_plugin_taskflow_checkboxes',
                'WHERE' => [
                    'plugin_taskflow_forms_id' => $config['id'],
                    'value'                    => $case,
                ],
            ])->current();

            if (!$mapping) {
                Toolbox::logInFile('taskflow', 'Aucun message configure pour la case "' . $case . '" (formulaire id=' . $config['id'] . ")\n");
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
