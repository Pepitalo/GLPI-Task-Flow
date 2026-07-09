<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginTaskflowForm extends CommonDBTM {

    public static $rightname = 'config';

    public static function getTypeName($nb = 0) {
        return _n('Formulaire suivi', 'Formulaires suivis', $nb, 'taskflow');
    }

    public function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Nom (libre)', 'taskflow') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'name');
        echo "</td>";
        echo "<td>" . __('ID de la question Formcreator (cases à cocher)', 'taskflow') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'plugin_formcreator_questions_id');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Commentaire') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='comment' rows='3' style='width:100%'>" .
             (isset($this->fields['comment']) ? $this->fields['comment'] : '') . "</textarea>";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
    }
}
