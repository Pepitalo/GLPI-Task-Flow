<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginTaskFlowCheckbox extends CommonDBTM {

    public static $rightname = 'config';

    public static function getTypeName($nb = 0) {
        return _n('Case à cocher', 'Cases à cocher', $nb, 'taskflow');
    }

    public function showForm($ID, $options = []) {
        $this->initForm($ID, $options);

        if (!isset($options['plugin_taskflow_forms_id']) && isset($this->fields['plugin_taskflow_forms_id'])) {
            $options['plugin_taskflow_forms_id'] = $this->fields['plugin_taskflow_forms_id'];
        }

        $this->showFormHeader($options);

        echo "<input type='hidden' name='plugin_taskflow_forms_id' value='" .
             (int) ($options['plugin_taskflow_forms_id'] ?? 0) . "'>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Valeur de la case cochée (telle que dans Formcreator)', 'taskflow') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'value');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Message à ajouter au ticket (HTML autorisé)', 'taskflow') . "</td>";
        echo "<td>";
        echo "<textarea name='message' rows='6' style='width:100%'>" .
             (isset($this->fields['message']) ? $this->fields['message'] : '') . "</textarea>";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
    }
}
