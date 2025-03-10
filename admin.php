<?php
// Check Piwigo configuration
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

global $template, $page;

// Include Piwigo admin functions
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// Set page title
$page['title'] = 'ChatGPT Image Captioner';

// Process form if submitted
if (isset($_POST['submit'])) {
    // This would be where you'd save settings if we had configurable options
    $page['infos'][] = 'Settings saved successfully.';
}

// Assign template variables
$template->assign(array(
    'PLUGIN_PATH' => get_root_url() . 'plugins/chatgpt/',
    'PLUGIN_VERSION' => '1.0.0',
));

// Display the template
$template->set_filename('plugin_admin_content', realpath(dirname(__FILE__)) . '/template/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>
