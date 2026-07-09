<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

$item = new PluginTaskflowForm();

if (isset($_POST['update'])) {
    Session::checkRight('config', UPDATE);
    $item->update($_POST);
    Html::back();
}

Html::header(PluginTaskflowForm::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'plugins');

$item->display(['id' => $_GET['id'] ?? 0]);

echo "<div class='center'>";
echo "<a href='form.php'>&laquo; " . __('Retour à la liste des formulaires', 'taskflow') . "</a>";
echo "</div>";

Html::footer();
