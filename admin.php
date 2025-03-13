<?php
// Check Piwigo configuration
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

// Include background processing functions
include_once(dirname(__FILE__) . '/include/background_process.php');

// Include Piwigo admin functions
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php');

// Set page title
$page['title'] = 'Gemini Image Captioner';

// Load configuration
$chatgpt_conf = isset($conf['chatgpt_captioner']) ? unserialize($conf['chatgpt_captioner']) : array(
    'api_key' => 'your-gemini-api-key',
    'model' => 'gemini-pro-vision',
    'user_prompt' => 'Please generate a detailed and accurate caption for this image. Describe the main subjects, setting, activities, and notable elements. Keep it under 3 sentences.'
);

// Process form if submitted
if (isset($_POST['submit'])) {
    // Get API key and model
    $api_key = isset($_POST['api_key']) ? $_POST['api_key'] : 'your-gemini-api-key';
    $model = isset($_POST['model']) ? $_POST['model'] : 'gemini-pro-vision';
    $user_prompt = isset($_POST['user_prompt']) ? $_POST['user_prompt'] : 'Please generate a detailed and accurate caption for this image. Describe the main subjects, setting, activities, and notable elements. Keep it under 3 sentences.';
    
    // Update configuration
    $chatgpt_conf['api_key'] = $api_key;
    $chatgpt_conf['model'] = $model;
    $chatgpt_conf['user_prompt'] = $user_prompt;
    
    // Save configuration
    conf_update_param('chatgpt_captioner', $chatgpt_conf, true);
    
    // Show success message
    $page['infos'][] = 'Settings saved successfully.';
}

// Handle AJAX requests for process status
if (isset($_GET['action']) && $_GET['action'] == 'get_processes') {
    // Return JSON response with process information
    header('Content-Type: application/json');
    echo json_encode(chatgpt_get_all_processes());
    exit;
}


// Assign template variables
$template->assign(array(
    'PLUGIN_PATH' => get_root_url() . 'plugins/chatgpt/',
    'PLUGIN_VERSION' => '1.0.0',
    'API_KEY' => $chatgpt_conf['api_key'],
    'MODEL' => $chatgpt_conf['model'],
    'USER_PROMPT' => $chatgpt_conf['user_prompt']
));

// Display the template
$template->set_filename('plugin_admin_content', realpath(dirname(__FILE__)) . '/template/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');

?>
