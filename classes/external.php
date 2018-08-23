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

    namespace block_mysites;
    use block_mysites\lib;
    use block_mysites\output\renderable;
    use context_user;
    use core\notification;
    use Exception;
    use external_api;
    use external_function_parameters;
    use external_multiple_structure;
    use external_single_structure;
    use external_value;
    use moodle_url;

    defined('MOODLE_INTERNAL') || die();

    require_once("{$CFG->libdir}/externallib.php");



    /**
     * Web service methods for block_mysites plugin
     */
    class external extends external_api
    {

        /**
         * Returns description of method parameters
         *
         * @return \external_function_parameters
         */
        public static function get_lists_for_username_parameters()
        {

            return new external_function_parameters(array(
                'username' => new external_value(PARAM_USERNAME, 'Moodle username', VALUE_REQUIRED, '', false)
            ));

        }


        /**
         * Returns description of method result value
         *
         * @return \external_multiple_structure
         */
        public static function get_lists_for_username_returns()
        {

            $courselistitem = new external_single_structure(array(
                'id'            => new external_value(PARAM_INT,        'Course Id'),
                'crn'           => new external_value(PARAM_TEXT,       'Course Ref. Number'),
                'shortname'     => new external_value(PARAM_TEXT,       'Course Shortname'),
                'backupcap'     => new external_value(PARAM_BOOL,       'Backup Capability'),
                'status'        => new external_value(PARAM_INT,        'Export Job Status'),
                'url'           => new external_value(PARAM_URL,        'Course URL')
            ));

            $backuplistitem = new external_single_structure(array(
                'filename'      => new external_value(PARAM_TEXT,       'File name'),
                'filesize'      => new external_value(PARAM_INT,        'File size'),
                'timecreated'   => new external_value(PARAM_INT,        'File create date'),
                'pathnamehash'  => new external_value(PARAM_ALPHANUM,   'Hash of Moodle repo file-path'),
                'contenthash'   => new external_value(PARAM_ALPHANUM,   'Hash of file content'),
                'downloadurl'   => new external_value(PARAM_URL,        'Download URL')
            ));

            return new external_single_structure(array(
                'courses' => new external_multiple_structure($courselistitem, 'List of courses'),
                'backups' => new external_multiple_structure($backuplistitem, 'List of backups')
            ), 'Courses and Backups');

        }


        /**
         * S2S: Fetch list of courses in which user has access and
         * their private backup files
         *
         * @param string $username Username for which to get list of enrolled courses
         * @return array
         */
        public static function get_lists_for_username($username)
        {
            global $CFG, $DB;


            $lists = array('courses' => array(), 'backups' => array());

            // Validate the input
            if (empty($username)) {
                return $lists;
            }

            // Get user record specified by the username
            $user = $DB->get_record('user', array('username' => $username));
            if (!$user) {
                return $lists;
            }

            $usercontext = context_user::instance($user->id);

            $courserecs = enrol_get_users_courses($user->id, true, array('id', 'shortname'), 'shortname ASC');
            if ($courserecs) {
                // Fetch list of queue entries for this user
                $queuerecs = $DB->get_records('block_mysites_queue', array('userid' => $user->id), "courseid, status", "courseid, status");
                if (!$queuerecs) {
                    $queuerecs = array();
                }
                foreach($courserecs as $courserec) {
                    $lists['courses'][] = array(
                        'id'        => $courserec->id,
                        'crn'       => $courserec->idnumber,
                        'shortname' => $courserec->shortname,
                        'backupcap' => has_capability('moodle/backup:backupcourse', \context_course::instance($courserec->id), $user->id, false),
                        'status'    => empty($queuerecs[$courserec->id]) ? 0 : $queuerecs[$courserec->id]->status,
                        'url'       => "{$CFG->wwwroot}/course/view.php?id={$courserec->id}"
                    );
                }
            }

            $files = get_file_storage()->get_area_files(context_user::instance($user->id)->id, 'user', 'backup', false, 'filename', false);
            foreach($files as $storedfile) {
                if ($storedfile->get_filename() == '.') {
                    continue;
                }
                $lists['backups'][] = array(
                    'filename'      => $storedfile->get_filename(),
                    'filesize'      => $storedfile->get_filesize(),
                    'timecreated'   => $storedfile->get_timecreated(),
                    'pathnamehash'  => $storedfile->get_pathnamehash(),
                    'contenthash'   => $storedfile->get_contenthash(),
                    'downloadurl'   => moodle_url::make_pluginfile_url($usercontext->id, 'user', 'backup', null, $storedfile->get_filepath(), $storedfile->get_filename(), true)->out(true)
                );
            }

            return $lists;

        } // get_lists_for_username


        /**
         * Returns description of method parameters
         *
         * @return \external_function_parameters
         */
        public static function request_action_parameters()
        {

            return new external_function_parameters(array(
                'action'   => new external_value(PARAM_ALPHA,       'Type of action',     VALUE_REQUIRED, '', false),
                'siteid'   => new external_value(PARAM_ALPHANUMEXT, 'Requesting site id', VALUE_REQUIRED, '', false),
                'username' => new external_value(PARAM_USERNAME,    'Moodle username',    VALUE_REQUIRED, '', false),
                'courseid' => new external_value(PARAM_INT,         'Moodle course id',   VALUE_REQUIRED,  0, false)
            ));

        }


        /**
         * Returns description of method result value
         *
         * @return external_value
         */
        public static function request_action_returns()
        {
            return new external_value(PARAM_INT, "Status code");
        }


        /**
         * S2S: Accept an action request from hub site.
         *
         * @param int $action One of the \block_mysites\lib::ACTION_X values
         * @param string $siteid Id of site making request
         * @param string $username Moodle username of user for whom request is made
         * @param int $courseid Moodle course id (on this site)
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function request_action($action, $siteid, $username, $courseid)
        {

            $config = lib::get_pluginconfig();

            if (empty($config->sites[$siteid]) || !$config->sites[$siteid]->cantakereq) {
                return lib::STATUS_REQUEST_FAILED;
            }

            try {
                switch($action) {
                    case 'backup' :
                        $response = lib::insert_backup_request($siteid, $username, $courseid);
                        break;
                    case 'cancel' :
                        $response = lib::cancel_backup_request($siteid, $username, $courseid);
                        break;
                    case 'reset' :
                        $response = lib::reset_backup_request($siteid, $username, $courseid);
                        break;
                    default :
                        $response = lib::STATUS_REQUEST_FAILED;
                }
            } catch (Exception $exc) {
                error_log($exc->getMessage());
                $response = lib::STATUS_REQUEST_FAILED;
            }

            return $response;

        }


        /**
         * Returns description of method parameters
         *
         * @return \external_function_parameters
         */
        public static function finish_upload_parameters()
        {

            return new external_function_parameters(array(
                'siteid'   => new external_value(PARAM_ALPHANUMEXT, 'Sender site id',   VALUE_REQUIRED, '', false),
                'username' => new external_value(PARAM_USERNAME,    'Moodle username',  VALUE_REQUIRED, '', false),
                'courseid' => new external_value(PARAM_INT,         'Moodle course id', VALUE_REQUIRED,  0, false),
                'itemid'   => new external_value(PARAM_INT,         'Draft item id',    VALUE_REQUIRED, '', false)
            ));

        }


        /**
         * Returns description of method result value
         *
         * @return external_value
         */
        public static function finish_upload_returns()
        {
            return new external_value(PARAM_INT, "Result code");
        }


        /**
         * S2S: Finish the external site's file upload by moving it from web
         * service user's draft file area to the target user's private backup
         * file area
         *
         * @param string $siteid Id of site from which file upload came
         * @param string $username Username of user for whom file upload made
         * @param int $courseid External site's Moodle course id
         * @param int $itemid Draft area item id
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function finish_upload($siteid, $username, $courseid, $itemid)
        {

            try {
                $response = lib::finish_upload($siteid, $username, $courseid, $itemid);
            }
            catch (Exception $exc) {
                error_log($exc->getMessage());
                $response = lib::STATUS_REQUEST_FAILED;
            }

            return $response;

        }


        /**
         * Specify input params for get_content_text
         *
         * @return external_function_parameters
         */
        public static function get_content_text_parameters()
        {
            return new external_function_parameters(array(
                'blockinstanceid' => new external_value(PARAM_INT, "Block instance id value.",          VALUE_REQUIRED, 0, NULL_NOT_ALLOWED),
                'siteindex' => new external_value(PARAM_INT, "Selected site tab index.",                VALUE_REQUIRED, 1, NULL_NOT_ALLOWED),
                'corbindex' => new external_value(PARAM_INT, "Selected course-or-backup button index.", VALUE_REQUIRED, 1, NULL_NOT_ALLOWED),
                'pageindex' => new external_value(PARAM_INT, "Selected page index.",                    VALUE_REQUIRED, 1, NULL_NOT_ALLOWED)
            ));
        }


        /**
         * Specify return type for get_content_text
         *
         * @return external_value
         */
        public static function get_content_text_returns()
        {
            return new external_value(PARAM_RAW, "Block content text (HTML markup)");
        }


        /**
         * AJAX: Get block content text for specified block instance id
         *
         * @param int $bid Block instance id
         * @throws Exception
         * @return string
         */
        public static function get_content_text($blockinstanceid, $siteindex, $corbindex, $pageindex)
        {
            global $DB, $OUTPUT, $PAGE;


            if (empty($siteindex)) $siteindex = 1;
            if (empty($corbindex)) $corbindex = 1;
            if (empty($pageindex)) $pageindex = 1;

            $PAGE->set_url('/my/');

            try {

                // Get the db record for the block instance
                $blockinstance = $DB->get_record('block_instances', array('id' => $blockinstanceid, 'blockname' => 'mysites'));
                if (!$blockinstance) {
                    throw new Exception(get_string('invalidblockinstance', 'error', get_string('pluginname', 'block_mysites')));
                }

                // Instantiate a block_instance object so we can access
                // its parent context to use in $PAGE
                $block = block_instance('mysites', $blockinstance, $PAGE);
                $PAGE->set_context($block->context->get_parent_context());

                $blockcontent = $PAGE->get_renderer('block_mysites')
                    ->render(new renderable($blockinstanceid, true, $siteindex, $corbindex, $pageindex));

                return $blockcontent->text;

            }
            catch (Exception $exc) {
                error_log($exc->getMessage());
                return $OUTPUT->notification($exc->getMessage(), notification::NOTIFY_ERROR);
            }

        }


        /**
         * Specify input params for get_course_select
         *
         * @return external_function_parameters
         */
        public static function get_course_select_parameters()
        {
            return new external_function_parameters(
                array('blockinstanceid' => new external_value(PARAM_INT, "Block instance id value.", NULL_NOT_ALLOWED))
            );
        }


        /**
         * Specify return type for get_course_select
         *
         * @return external_value
         */
        public static function get_course_select_returns()
        {
            return new external_value(PARAM_RAW, "Select list of courses (HTML markup)");
        }


        /**
         * AJAX: Get <select> list of courses for which user has restore capability
         *
         * @param int $bid Block instance id
         * @throws Exception
         * @return string
         */
        public static function get_course_select($blockinstanceid)
        {
            global $DB, $OUTPUT, $PAGE, $USER;


            try {

                $PAGE->set_url('/my/');

                // Get the db record for the block instance
                $blockinstance = $DB->get_record('block_instances', array('id' => $blockinstanceid, 'blockname' => 'mysites'));
                if (!$blockinstance) {
                    throw new Exception(get_string('invalidblockinstance', 'error', get_string('pluginname', 'block_mysites')));
                }

                // Instantiate a block_instance object so we can access
                // its config and rendering methods
                $block = block_instance('mysites', $blockinstance, $PAGE);
                $PAGE->set_context($block->context->get_parent_context());

                $courses = get_user_capability_course('moodle/restore:restorecourse', $USER->id, false, 'shortname, ctxid', 'shortname');
                return $PAGE->get_renderer('block_mysites')->render_course_select($courses, context_user::instance($USER->id)->id);

            }
            catch (Exception $exc) {
                error_log($exc->getMessage());
                return $OUTPUT->notification($exc->getMessage(), notification::NOTIFY_ERROR);
            }

        }


        /**
         * Specify input params for send_request_action
         *
         * @return external_function_parameters
         */
        public static function send_request_action_parameters()
        {
            return new external_function_parameters(
                array(
                    'blockinstanceid'   => new external_value(PARAM_INT,         "Request type", NULL_NOT_ALLOWED),
                    'action'            => new external_value(PARAM_ALPHA,       "Request type", NULL_NOT_ALLOWED),
                    'siteid'            => new external_value(PARAM_ALPHANUMEXT, "Site id",      NULL_NOT_ALLOWED),
                    'courseid'          => new external_value(PARAM_INT,         "Course id",    NULL_NOT_ALLOWED)
                )
            );
        }


        /**
         * Specify return type for send_request_action
         *
         * @return external_value
         */
        public static function send_request_action_returns()
        {
            return new external_value(PARAM_RAW, "Status div markup");
        }


        /**
         * AJAX: Get block content text for specified block instance id AJAX
         *
         * @param int $bid Block instance id
         * @return array Array with keys: status (int), label (string), class (string), action (string)
         */
        public static function send_request_action($blockinstanceid, $action, $siteid, $courseid)
        {
            global $DB, $OUTPUT, $PAGE, $USER;


            $config = lib::get_pluginconfig();
            if (empty($config->sites[$siteid]) || !$config->sites[$siteid]->cansendreq) {
                return lib::STATUS_REQUEST_FAILED;
            }

            try {

                $PAGE->set_url('/my/');

                // Get the db record for the block instance
                $blockinstance = $DB->get_record('block_instances', array('id' => $blockinstanceid, 'blockname' => 'mysites'));
                if (!$blockinstance) {
                    throw new Exception(get_string('invalidblockinstance', 'error', get_string('pluginname', 'block_mysites')));
                }

                if ($action == 'clear') {
                    // Clear status for an already finished import
                    $status = lib::clear_import($action, $USER->id, $siteid, $courseid);
                } else {
                    // Send the request and then render the appropriate
                    // status div to return to the browser.
                    $status = lib::send_request_action($action, $USER->username, $siteid, $courseid);
                }

                // Instantiate a block_instance object so we can access
                // its parent context to use in $PAGE
                $block = block_instance('mysites', $blockinstance, $PAGE);
                $PAGE->set_context($block->context->get_parent_context());
                lib::clear_user_cache($USER->id);

                return $PAGE->get_renderer('block_mysites')->render_course_status_div($status, $siteid, $courseid);

            }
            catch (Exception $exc) {
                error_log($exc->getMessage());
                return $OUTPUT->notification($exc->getMessage(), notification::NOTIFY_ERROR);
            }

        }

    } // class
