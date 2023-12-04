<?php
$plugin_path = wa('shop')->getConfig()->getPluginPath('syrrss');
waFiles::delete( $plugin_path . '/templates/actions/settings');
waFiles::delete( $plugin_path . '/lib/actions/backend/shopSyrrssPluginSettings.action.php');
