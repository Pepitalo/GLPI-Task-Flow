<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

global $DB;

header('Content-Type: application/json');

// forms_id est passé en query string (juste un entier, aucun risque d'échappement ici).
$forms_id = (int) ($_GET['forms_id'] ?? 0);

// On lit directement le corps brut de la requête (envoyé en JSON depuis config.php).
// C'est la partie importante : contrairement à $_POST, php://input n'est JAMAIS
// altéré par la couche de compatibilité de GLPI qui applique addslashes() à
// $_GET/$_POST/$_REQUEST. C'est cette double-couche d'échappement qui corrompait
// les apostrophes et guillemets contenus dans les messages.
$raw_body = file_get_contents('php://input');
$payload  = json_decode($raw_body, true);

if (!$forms_id || !is_array($payload) || !isset($payload['blocks']) || !is_array($payload['blocks'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Requête invalide : ' . json_last_error_msg(),
    ]);
    exit;
}

$blocks = $payload['blocks'];

// -------------------------------------------------------------------------
// NETTOYAGE & RÉINSERTION (stratégie : on remplace entièrement la config de
// ce formulaire à chaque sauvegarde, pas de clé naturelle pratique à matcher
// finement entre l'ancien et le nouvel état côté blocs).
// -------------------------------------------------------------------------
$configs = $DB->request([
    'FROM'  => 'glpi_plugin_taskflow_forms',
    'WHERE' => ['plugin_formcreator_forms_id' => $forms_id],
]);

$config_ids = [];
foreach ($configs as $c) {
    $config_ids[] = $c['id'];
}

$errors = [];
$debug  = [];

if (!empty($config_ids)) {
    if (!$DB->delete('glpi_plugin_taskflow_checkboxes', ['plugin_taskflow_forms_id' => $config_ids])) {
        $errors[] = 'Suppression checkboxes : ' . $DB->error();
    }
}
if (!$DB->delete('glpi_plugin_taskflow_forms', ['plugin_formcreator_forms_id' => $forms_id])) {
    $errors[] = 'Suppression forms : ' . $DB->error();
}
if (!$DB->delete('glpi_plugin_taskflow_always_blocks', ['plugin_formcreator_forms_id' => $forms_id])) {
    $errors[] = 'Suppression always_blocks : ' . $DB->error();
}

$ordre = 0;

foreach ($blocks as $index => $block) {
    $type = $block['type'] ?? '';

    if ($type === 'always') {
        $message = trim($block['message'] ?? '');

        if ($message !== '') {
            $ok = $DB->insert('glpi_plugin_taskflow_always_blocks', [
                'plugin_formcreator_forms_id' => $forms_id,
                'message'                     => addslashes($message),
                'ordre'                       => $ordre,
                'date_creation'               => date('Y-m-d H:i:s'),
            ]);
            if (!$ok) {
                $errors[] = "Bloc #$index (toujours envoyé) : insert échoué - " . $DB->error();
            } else {
                $debug[] = "Bloc #$index (toujours envoyé) inséré, message = " . mb_substr($message, 0, 40);
            }
            $ordre++;
        } else {
            $debug[] = "Bloc #$index (toujours envoyé) ignoré : message vide après trim()";
        }
    } elseif ($type === 'question') {
        $questions_id = (int) ($block['questions_id'] ?? 0);
        if (!$questions_id) {
            $debug[] = "Bloc #$index (question) ignoré : questions_id manquant ou nul";
            continue;
        }

        $has_message = false;
        foreach (($block['mappings'] ?? []) as $mapping) {
            if (trim($mapping['message'] ?? '') !== '') {
                $has_message = true;
                break;
            }
        }

        if ($has_message) {
            $question = $DB->request([
                'FROM'  => 'glpi_plugin_formcreator_questions',
                'WHERE' => ['id' => $questions_id],
            ])->current();

            $name = $question['name'] ?? ('Question #' . $questions_id);

            $ok = $DB->insert('glpi_plugin_taskflow_forms', [
                'name'                            => addslashes($name),
                'plugin_formcreator_forms_id'     => $forms_id,
                'plugin_formcreator_questions_id' => $questions_id,
                'ordre'                           => $ordre,
                'date_creation'                   => date('Y-m-d H:i:s'),
            ]);

            if (!$ok) {
                $errors[] = "Bloc #$index (question #$questions_id) : insert config échoué - " . $DB->error();
                continue;
            }

            $config_id = $DB->insertId();
            $debug[] = "Bloc #$index (question #$questions_id) inséré, config_id = $config_id";

            foreach (($block['mappings'] ?? []) as $mapping) {
                $value   = $mapping['value'] ?? '';
                $message = trim($mapping['message'] ?? '');

                if ($value !== '' && $message !== '') {
                    $ok = $DB->insert('glpi_plugin_taskflow_checkboxes', [
                        'plugin_taskflow_forms_id' => $config_id,
                        'value'                    => addslashes($value),
                        'message'                  => addslashes($message),
                        'date_creation'            => date('Y-m-d H:i:s'),
                    ]);
                    if (!$ok) {
                        $errors[] = "Bloc #$index / valeur '$value' : insert checkbox échoué - " . $DB->error();
                    } else {
                        $debug[] = "  -> valeur '$value' insérée, message = " . mb_substr($message, 0, 40);
                    }
                }
            }
            $ordre++;
        } else {
            $debug[] = "Bloc #$index (question #$questions_id) ignoré : aucun message non-vide dans mappings";
        }
    }
}

if (!empty($errors)) {
    echo json_encode([
        'status'  => 'error',
        'message' => implode(' | ', $errors),
        'debug'   => $debug,
    ]);
    exit;
}

echo json_encode(['status' => 'ok', 'debug' => $debug]);
