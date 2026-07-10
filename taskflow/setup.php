<?php
define('PLUGIN_TASKFLOW_VERSION', '1.0.0');
 
function plugin_init_taskflow() {
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['taskflow'] = true;
 
    $PLUGIN_HOOKS['item_add']['taskflow'] = [
        'PluginFormcreatorFormAnswer' => 'plugin_taskflow_formanswer_add'
    ];
 
    $PLUGIN_HOOKS['config_page']['taskflow'] = 'front/config.php';
 
    Plugin::registerClass('PluginTaskflowForm');
    Plugin::registerClass('PluginTaskflowCheckbox');
}
 
function plugin_version_taskflow() {
    return [
        'name'         => 'TaskFlow',
        'version'      => PLUGIN_TASKFLOW_VERSION,
        'author'       => 'Antonio Matta',
        'license'      => 'GPLv2+',
        'requirements' => [
            'glpi' => ['min' => '10.0.0', 'max' => '10.99.99'],
        ],
    ];
}
 
function plugin_taskflow_check_prerequisites() { return true; }
function plugin_taskflow_check_config($verbose = false) { return true; }
 
