<?php

    // This file is part of Moodle - http://moodle.org/
    //
    // Moodle is free software: you can redistribute it and/or modify
    // it under the terms of the GNU General Public License as published by
    // the Free Software Foundation, either version 3 of the License, or
    // (at your option) any later version.
    //
    // Moodle is distributed in the hope that it will be useful,
    // but WITHOUT ANY WARRANTY; without even the implied warranty of
    // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    // GNU General Public License for more details.
    //
    // You should have received a copy of the GNU General Public License
    // along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

    /**
     * block_mysites
     *
     * @author      Fred Woolard <woolardfa@appstate.edu>
     * @copyright   (c) 2018 Appalachian State Universtiy, Boone, NC
     * @license     GNU General Public License version 3
     * @package     block_mysites
     */

    defined('MOODLE_INTERNAL') || die();



    // Included from (plugininfo)block::load_settings(). Globals
    // declared: $CFG, $USER, $DB, $OUTPUT, and $PAGE. Three arguments
    // passed into the function: $adminroot, $parentnodename, and
    // $hassiteconfig. Local scoped variables assigned:
    // $plugininfo = $this,
    // $block = $this, where $this is the plugininfo instance,
    // $ADMIN = $adminroot,
    // and $settings = new admin_settingpage() instance.

    if ($hassiteconfig) {

        $pluginname = 'block_mysites';


        $field = "site";
        $adminSetting = new admin_setting_heading(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname));
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "thissiteid";
        $adminSetting = new admin_setting_configtext_with_maxlength(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname),
            '', PARAM_ALPHANUMEXT, 20, 20);
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "blocktitle";
        $adminSetting = new admin_setting_configtext_with_maxlength(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname),
            'MySites', PARAM_TEXT, 30, 50);
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "connection";
        $adminSetting = new admin_setting_heading(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname));
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "sites";
        $adminSetting = new admin_setting_configtextarea(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname),
            "");
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "display";
        $adminSetting = new admin_setting_heading(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname));
        $settings->add($adminSetting);
        unset($adminSetting);

        $field = "showbackups";
        $adminSetting = new admin_setting_configcheckbox(
            "{$pluginname}/{$field}",
            get_string("{$field}_lbl",  $pluginname),
            get_string("{$field}_desc", $pluginname),
            "");
        $settings->add($adminSetting);
        unset($adminSetting);

    }
