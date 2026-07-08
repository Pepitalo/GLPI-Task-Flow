<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::header(PluginTaskFlowForm::getTypeName(2), $_SERVER['PHP_SELF'], 'config', 'plugins');

// Suppression
if (isset($_GET['delete'])) {
    Session::checkRight('config', UPDATE);
    $form = new PluginTaskFlowForm();
    $form->delete(['id' => $_GET['delete']]);
    Html::redirect($_SERVER['PHP_SELF']);
}

// Ajout
if (isset($_POST['add'])) {
    Session::checkRight('config', UPDATE);
    $form = new PluginTaskFlowForm();
    $form->add($_POST);
    Html::redirect($_SERVER['PHP_SELF']);
}

// Duplication
if (isset($_GET['duplicate'])) {
    Session::checkRight('config', UPDATE);

    $source = new PluginTaskFlowForm();
    if ($source->getFromDB($_GET['duplicate'])) {
        $new_form = new PluginTaskFlowForm();
        $new_id = $new_form->add([
            'name'                            => $source->fields['name'] . ' (copie)',
            'plugin_formcreator_questions_id' => $source->fields['plugin_formcreator_questions_id'],
            'comment'                         => $source->fields['comment'],
        ]);

        if ($new_id) {
            global $DB;
            $iterator = $DB->request([
                'FROM'  => 'glpi_plugin_taskflow_checkboxes',
                'WHERE' => ['plugin_taskflow_forms_id' => $_GET['duplicate']],
            ]);
            foreach ($iterator as $row) {
                $checkbox = new PluginTaskFlowCheckbox();
                $checkbox->add([
                    'plugin_taskflow_forms_id' => $new_id,
                    'value'                    => $row['value'],
                    'message'                  => $row['message'],
                ]);
            }

            Html::redirect('form.form.php?id=' . $new_id);
        }
    }
    Html::redirect($_SERVER['PHP_SELF']);
}

echo "<div class='center'>";
echo "<h2>" . __('Ajouter un formulaire suivi', 'taskflow') . "</h2>";
echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Nouveau formulaire suivi', 'taskflow') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Nom (libre)', 'taskflow') . "</td>";
echo "<td><input type='text' name='name' size='40'></td>";
echo "</tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('ID de la question Formcreator (cases à cocher)', 'taskflow') . "</td>";
echo "<td><input type='number' name='plugin_formcreator_questions_id' size='10'></td>";
echo "</tr>";
echo "<tr class='tab_bg_1'>";
echo "<td colspan='2' class='center'>";
echo "<input type='submit' name='add' value='" . __('Ajouter') . "' class='submit'>";
echo "</td>";
echo "</tr>";
echo "</table>";
Html::closeForm();
echo "</div>";

echo "<div class='center'>";
echo "<h2>" . __('Formulaires suivis existants', 'taskflow') . "</h2>";
echo "<table class='tab_cadre_fixehov'>";
echo "<tr><th>ID</th><th>" . __('Nom') . "</th><th>" . __('ID question', 'taskflow') . "</th><th></th><th></th><th></th><th></th></tr>";

global $DB;
$iterator = $DB->request(['FROM' => 'glpi_plugin_taskflow_forms', 'ORDER' => 'id']);
foreach ($iterator as $row) {
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . Html::entities_deep($row['name']) . "</td>";
    echo "<td>" . $row['plugin_formcreator_questions_id'] . "</td>";
    echo "<td><a href='checkbox.php?plugin_taskflow_forms_id=" . $row['id'] . "'>" .
         __('Gérer les cases à cocher', 'taskflow') . "</a></td>";
    echo "<td><a href='form.form.php?id=" . $row['id'] . "'>" .
         __('Modifier') . "</a></td>";
    echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?duplicate=" . $row['id'] . "'>" .
         __('Dupliquer', 'taskflow') . "</a></td>";
    echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?delete=" . $row['id'] .
         "' onclick=\"return confirm('" . __('Confirmer la suppression ?') . "');\">" .
         __('Supprimer') . "</a></td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

Html::footer();

