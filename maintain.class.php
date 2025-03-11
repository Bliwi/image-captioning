<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

include_once(PHPWG_PLUGINS_PATH . 'chatgpt/include/queue.inc.php');

class chatgpt_maintain extends PluginMaintain {
  function install($plugin_version, &$errors = []) {
    chatgpt_create_queue_table();
  }

  function update($old_version, $new_version, &$errors = []) {
    $this->install($new_version, $errors);
  }

  function uninstall() {
    // No special uninstallation needs
  }
}
?>
