<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

$forms_id = (int) ($_GET['plugin_taskflow_forms_id'] ?? 0);

$parent = new PluginTaskFlowForm();
if (!$forms_id || !$parent->getFromDB($forms_id)) {
    Html::displayNotFoundError();
}

Html::header(PluginTaskFlowCheckbox::getTypeName(2), $_SERVER['PHP_SELF'], 'config', 'plugins');

// Suppression
if (isset($_GET['delete'])) {
    Session::checkRight('config', UPDATE);
    $checkbox = new PluginTaskFlowCheckbox();
    $checkbox->delete(['id' => $_GET['delete']]);
    Html::redirect($_SERVER['PHP_SELF'] . '?plugin_taskflow_forms_id=' . $forms_id);
}

// Ajout
if (isset($_POST['add'])) {
    Session::checkRight('config', UPDATE);
    $checkbox = new PluginTaskFlowCheckbox();
    $_POST['plugin_taskflow_forms_id'] = $forms_id;
    $checkbox->add($_POST);
    Html::redirect($_SERVER['PHP_SELF'] . '?plugin_taskflow_forms_id=' . $forms_id);
}

echo "<div class='center'>";
echo "<h2>" . sprintf(__('Cases à cocher pour : %s', 'taskflow'), Html::entities_deep($parent->fields['name'])) . "</h2>";

echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "?plugin_taskflow_forms_id=" . $forms_id . "'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Ajouter une case à cocher', 'taskflow') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Valeur exacte de la case (telle que dans Formcreator)', 'taskflow') . "</td>";
echo "<td><input type='text' name='value' size='40'></td>";
echo "</tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Message à ajouter au ticket (HTML autorisé)', 'taskflow') . "</td>";
echo "<td><textarea name='message' rows='5' cols='60'></textarea></td>";
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
echo "<table class='tab_cadre_fixehov'>";
echo "<tr><th>ID</th><th>" . __('Valeur') . "</th><th>" . __('Message') . "</th><th></th></tr>";

global $DB;
$iterator = $DB->request([
    'FROM'  => 'glpi_plugin_taskflow_checkboxes',
    'WHERE' => ['plugin_taskflow_forms_id' => $forms_id],
    'ORDER' => 'id',
]);
foreach ($iterator as $row) {
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . Html::entities_deep($row['value']) . "</td>";
    echo "<td>" . $row['message'] . "</td>";
    echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?plugin_taskflow_forms_id=" . $forms_id .
         "&delete=" . $row['id'] . "' onclick=\"return confirm('" .
         __('Confirmer la suppression ?') . "');\">" . __('Supprimer') . "</a></td>";
    echo "</tr>";
}
echo "</table>";
echo "<br><a href='form.php'>&laquo; " . __('Retour à la liste des formulaires', 'taskflow') . "</a>";
echo "</div>";

Html::footer();

