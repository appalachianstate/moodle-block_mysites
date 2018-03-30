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



    $services = array(

        'MySites' => array(
            'functions' => array(
                'block_mysites_getlistsforusername',
                'block_mysites_requestaction',
                'block_mysites_finishupload'
            ),
            'requiredcapability' => '',
            'restrictedusers'    => 1,
            'enabled'            => 0)

    );

    $functions = array(

        // SITE TO STIE - Service to service call, collect user enrolls
        'block_mysites_getlistsforusername' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'get_lists_for_username',
            'classpath'     => '',
            'description'   => 'Get lists of courses in which user is enrolled and private backup files.',
            'type'          => 'read',
            'ajax'          => false,
            'services'      => 'MySites'),

        // SITE TO STIE - Service to service call, receive action request from remote site
        'block_mysites_requestaction' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'request_action',
            'classpath'     => '',
            'description'   => 'Receive action request from external (hub) site.',
            'type'          => 'write',
            'ajax'          => false,
            'services'      => 'MySites'),

        // SITE TO STIE - Service to service call, receive request to finish file upload
        'block_mysites_finishupload' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'finish_upload',
            'classpath'     => '',
            'description'   => 'Finish the file upload.',
            'type'          => 'write',
            'ajax'          => false,
            'services'      => 'MySites'),

        // AJAX - call for block content refresh
        'block_mysites_getcontenttext' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'get_content_text',
            'classpath'     => '',
            'description'   => 'Refresh the block content text from an AJAX call.',
            'type'          => 'read',
            'ajax'          => true),

        // AJAX - call for sending request to external site
        'block_mysites_sendrequestaction' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'send_request_action',
            'classpath'     => '',
            'description'   => 'Submit actions for external course backup.',
            'type'          => 'read',
            'ajax'          => true),

        // AJAX - call for sending request to external site
        'block_mysites_getcourseselect' => array(
            'classname'     => 'block_mysites\external',
            'methodname'    => 'get_course_select',
            'classpath'     => '',
            'description'   => 'Get list of courses where user can restore.',
            'type'          => 'read',
            'ajax'          => true)

    );
