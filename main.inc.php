<?php
/*
Plugin Name: ChatGPT Image Captioner
Version: 1.2.0
Description: Uses ChatGPT API (GPT-4o) to automatically generate captions for images
and add them to image descriptions.
Author: bliwi
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('CHATGPT_ID',      basename(dirname(__FILE__)));
define('CHATGPT_PATH' ,   PHPWG_PLUGINS_PATH . CHATGPT_ID . '/');
define('CHATGPT_ADMIN',   get_root_url() . 'admin.php?page=plugin-' . CHATGPT_ID);

// Include required files
include_once(CHATGPT_PATH . 'include/functions.inc.php');
include_once(CHATGPT_PATH . 'include/queue.inc.php');

// Ensure queue table exists
chatgpt_create_queue_table();

// Register event handler to process the queue on page load
add_event_handler('init', 'chatgpt_process_queue', EVENT_HANDLER_PRIORITY_NEUTRAL);

if (defined('IN_ADMIN'))
{
  add_event_handler('get_admin_plugin_menu_links', 'chatgpt_admin_menu');
  
  // Add menu entry to admin panel
  function chatgpt_admin_menu($menu) {
    $menu[] = array(
      'NAME' => 'ChatGPT Captioner',
      'URL' => get_admin_plugin_menu_link(dirname(__FILE__) . '/admin.php'),
    );
    return $menu;
  }
  
  // Add option to batch manager dropdown
  add_event_handler('loc_begin_element_set_global', 'chatgpt_add_batch_option');
  function chatgpt_add_batch_option() {
    global $template;
    
    // Add our action to the list of available actions in the batch manager
    $template->append('element_set_global_plugins_actions', array(
      'ID' => 'chatgpt_caption',
      'NAME' => 'Caption with ChatGPT',
      'ICON' => 'icon-comment'
    ));
  }

  // Handle the batch action when it's selected
  add_event_handler('element_set_global_action', 'chatgpt_handle_batch_action', EVENT_HANDLER_PRIORITY_NEUTRAL, 2);
}
?>
