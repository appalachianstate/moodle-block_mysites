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



    $string['pluginname']                   = 'MySites';
    $string['taskname']                     = 'MySites background processing task';

    $string['mysites:addinstance']          = 'Add new MySites block';
    $string['mysites:myaddinstance']        = 'Add new MySites block to my page';
    $string['cachedef_cache']               = 'MySites session cache';

    $string['nositeconfig']                 = 'MySites configuration incomplete. Please contact your system administrator for assistance.';
    $string['wscallerror']                  = 'Error calling external site {$a}';

    $string['site_lbl']                     = 'Site';
    $string['site_desc']                    = 'Site configuration values';
    $string['thissiteid_lbl']               = 'This site\'s id';
    $string['thissiteid_desc']              = 'Unique ten char value (A-Z0-9) to identify this site and distinguish it from others.';
    $string['blocktitle_lbl']               = 'Block title';
    $string['blocktitle_desc']              = 'Title appearing in block header, if left blank \'MySites\' is used.';
    $string['connection_lbl']               = 'Web service connection';
    $string['connection_desc']              = 'Values used to connect to the MySites API web service.';
    $string['sites_lbl']                    = 'Sites List';
    $string['sites_desc']                   = 'List of sites from which to display user course enrollment information. Comma separated, optionally quoted fields: siteid, tab label, apikey, url, upload limit (e.g. 200M, optional), send requests to site (Y/N, optional), accept requests from site (Y/N, optional)';
    $string['display_lbl']                  = 'Display';
    $string['display_desc']                 = 'Display configuration values';
    $string['showbackups_lbl']              = 'Display backups';
    $string['showbackups_desc']             = 'Display list of user private backups from external sites.';

    $string['backup']                       = 'Request Backup';
    $string['reset']                        = 'Reset';
    $string['cancel']                       = 'Cancel Request';
    $string['restore']                      = 'Restore Archive';

    $string['status_-1']                    = 'Request failed!';
    $string['status_0']                     = '';
    $string['status_1']                     = 'Backup pending...';
    $string['status_2']                     = 'Backup in proress...';
    $string['status_3']                     = 'Backup failed!';
    $string['status_4']                     = 'Transfer pending...';
    $string['status_5']                     = 'Transfer in progress...';
    $string['status_6']                     = 'Transfer completed...';
    $string['status_7']                     = 'Transfer failed!';
    $string['status_8']                     = 'File too large!';
    $string['status_9']                     = 'Backup available.';
