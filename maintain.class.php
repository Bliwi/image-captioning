<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class chatgpt_maintain extends PluginMaintain {
  function install($plugin_version, &$errors = []) {
    // No special installation needs
  }

  function update($old_version, $new_version, &$errors = []) {
    $this->install($new_version, $errors);
  }

  function uninstall() {
    // No special uninstallation needs
  }
}
?>
