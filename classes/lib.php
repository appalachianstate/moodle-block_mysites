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
    use \backup;
    use \backup_check;
    use \backup_controller;
    use \backup_plan_dbops;
    use \context_user;
    use \Exception;
    use \stdClass;

    defined('MOODLE_INTERNAL') || die();

    require_once("{$CFG->dirroot}/webservice/rest/lib.php");
    require_once("{$CFG->dirroot}/lib/filelib.php");



    abstract class lib
    {

        const STATUS_REQUEST_FAILED         = -1;

        const STATUS_REQUEST_NONE           = 0;
        const STATUS_BACKUP_PENDING         = 1;
        const STATUS_BACKUP_INPROGRESS      = 2;
        const STATUS_BACKUP_FAILED          = 3;
        const STATUS_TRANSFER_PENDING       = 4;
        const STATUS_TRANSFER_INPROGRESS    = 5;
        const STATUS_TRANSFER_COMPLETED     = 6;
        const STATUS_TRANSFER_FAILED        = 7;
        const STATUS_TRANSFER_FILETOOBIG    = 8;
        const STATUS_BACKUP_AVAILABLE       = 9;

        const ACTION_BACKUP                 = 'backup';
        const ACTION_CANCEL                 = 'cancel';
        const ACTION_RESET                  = 'reset';
        const ACTION_RESTORE                = 'restore';



        /**
         * Get the singleton plugin configs.
         *
         * @return stdClass
         */
        public static function get_pluginconfig()
        {
            static $pluginconfig = null;


            if ($pluginconfig != null) {
                return $pluginconfig;
            }

            $config = get_config('block_mysites');
            if (!$config) {
                return null;
            }

            $config->sites = self::parse_sites_config($config->sites);
            if (self::plugin_configured($config)) {
                $pluginconfig = $config;
            }

            return $pluginconfig;

        }


        /**
         * Check if connection configs available.
         *
         * @param stdClass $config Plugin config returned from get_config().
         * @return bool
         */
        public static function plugin_configured($config = null)
        {

            if ($config == null) {
                $config = self::get_pluginconfig();
            }

            if (empty($config) || !is_object($config) || empty($config->sites) || empty($config->thissiteid)) {
                return false;
            }

            return true;

        }


        /**
         * Parse the external sites config string
         *
         * @param string $siteconfig
         * @return mixed array or null
         */
        private static function parse_sites_config($sitesconfig)
        {

            $sites = array();

            if (empty($sitesconfig)) {
                return $sites;
            }

            $lines = preg_split('/\r\n|\n\r|[\r\n]/', $sitesconfig, 0, PREG_SPLIT_NO_EMPTY);
            if (empty($lines)) {
                return $sites;
            }

            $regex = '/^(?:(?:"?([a-z0-9_-]{1,20})"?)|\1)\s*,\s*(?:(?:"?([a-z0-9\. ,-]+)"?)|\2)'
                   . '\s*,\s*(?:(?:"?([a-f0-9]{32})"?)|\3)\s*,\s*(?:(?:"?([^,"\s]+)"?)|\4)'
                   . '(?:\s*,\s*(?:(?:"?([^,"\s]+)"?)|\5)?(?:\s*,\s*(?:(?:"?([ny]?)"?)|\6)'
                   . '(?:\s*,\s*(?:"?([ny]?)"?)|\7)?)?)?\s*$/iU';

            foreach ($lines as $line) {

                $matches = array();
                preg_match($regex, $line, $matches);
                if (empty($matches)) {
                    continue;
                }

                $url = filter_var($matches[4], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED);
                if (!$url) {
                    continue;
                }

                $site             = new \stdClass();
                $site->id         = $matches[1];
                $site->label      = $matches[2];
                $site->token      = $matches[3];
                $site->url        = $url;
                $site->maxupload  = (isset($matches[5]) ? get_real_size($matches[5]) : 0);
                $site->cansendreq = (isset($matches[6]) ? ($matches[6] == 'Y' || $matches[6] == 'y') : true);
                $site->cantakereq = (isset($matches[7]) ? ($matches[7] == 'Y' || $matches[7] == 'y') : true);
                $site->count      = 0;

                $sites[$site->id] = $site;

            }

            return $sites;

        }


        /**
         * Remove a user's cached entry
         *
         * @param int $userid Moodle user id
         */
        public static function clear_user_cache($userid)
        {
            global $DB;


            try {
                $DB->delete_records('block_mysites', array('userid' => $userid));
            } catch (Exception $ex) {
                // Squelch
            }

        }


        /**
         * Remove old cached entries
         *
         * @param int $minutesold Specify the age of cache entries to be cleared
         */
        public static function clear_stale_caches($minutesold = 5)
        {
            global $DB;


            $where = "timecreated < :timecreated";
            $params = array('timecreated' => time() - ($minutesold * MINSECS));

            try {
                $DB->delete_records_select('block_mysites', $where, $params);
            } catch (Exception $ex) {
                // Squelch
            }

        }


        /**
         * Get a list of courses available to the user from the configured external sites
         *
         * @param stdClass  $user User for whom to fetch enrollments
         * @param bool      $refresh If true, clears the cache
         * @return array    Array of three elements: courselists (array of stdClass), tablabels (array of string), errormsg (string)
         */
        public static function get_lists(stdClass $user, $refresh = false)
        {
            global $DB;


            // If plugin not configured, nothing to fetch.
            if (!self::plugin_configured()) {
                return array(array(), array(), array(), get_string('nositeconfig', 'block_mysites'));
            }

            // See what's in the DB.
            $cachedrec = $DB->get_record('block_mysites', array('userid' => $user->id));
            if ($cachedrec && !$refresh) {
                $blob = (array)unserialize(base64_decode($cachedrec->data));
                return array($blob['tablabels'], $blob['courselists'], $blob['backuplists'], '');
            }

            $tablabels = array(); $courselists = array(); $backuplists = array();

            // Get the courses in which user has enrollments for
            // each of the configured external sites.
            $sites = self::get_pluginconfig()->sites;
            foreach ($sites as $siteid => $site) {
                $tablabels[$siteid] = $site->label;
                try {
                    $lists = self::get_lists_for_site($site, $user->username);
                } catch (Exception $ex) {
                    return array(array(), array(), array(), get_string('wscallerror', 'block_mysites', $siteid));
                }
                $courselists[$siteid] = $lists['courses'];
                $backuplists[$siteid] = $lists['backups'];
            }

            // Get list of imports for this user to augment
            // list collected from web service calls.
            $importrecs = $DB->get_records('block_mysites_import', array('userid' => $user->id));
            if ($importrecs) {

                $fs = get_file_storage();
                $context = context_user::instance($user->id);

                foreach($importrecs as $importrec) {

                    // Is there an entry for that course in current list
                    if (   empty($courselists[$importrec->siteid])
                        || empty($courselists[$importrec->siteid][$importrec->sitecourseid])) {
                        // Likely that site no longer configured, or
                        // user no longer has access to course
                        $DB->delete_records('block_mysites_import', array('id' => $importrec->id));
                        continue;
                    }

                    $courseref = &$courselists[$importrec->siteid][$importrec->sitecourseid];

                    // What status is external site indicating, only
                    // show available if no other status set.
                    if ($courseref->status != self::STATUS_REQUEST_NONE) {
                        continue;
                    }

                    // Does the file still exist in user's backup area
                    $storedfile = $fs->get_file($context->id, 'user', 'backup', 0, '/', $importrec->filename);
                    if (!$storedfile) {
                        // File has been deleted or moved to subdir, so
                        // clear this import so user can resubmit
                        $DB->delete_records('block_mysites_import', array('id' => $importrec->id));
                        continue;
                    }

                    // Mark it as being available since file is stil in
                    // user's backup area and not showing any ongoing
                    // or lingering request.
                    $courseref->status = self::STATUS_BACKUP_AVAILABLE;
                    $courseref->filename = $importrec->filename;
                    $courseref->timecreated = $importrec->timecreated;

                } // foreach($importrecs)

            } // if ($importrecs)

            // Do not want each site's courselist and backuplist keyed by
            // courseid/pathnamehash for paging purposes later, should be
            // ordinal, i.e. 0, 1, 2, ...
            foreach (array_keys($sites) as $siteid) {
                $courselists[$siteid] = array_values($courselists[$siteid]);
                $backuplists[$siteid] = array_values($backuplists[$siteid]);
            }

            // Insert or update the cache (mdl_block_mysites)
            if ($cachedrec == false) {
                $cachedrec = new \stdClass();
                $cachedrec->userid = $user->id;
            }
            $cachedrec->timecreated = time();
            $cachedrec->data = base64_encode(serialize(array('tablabels' => $tablabels, 'courselists' => $courselists, 'backuplists' => $backuplists)));

            if (empty($cachedrec->id)) {
                $DB->insert_record('block_mysites', $cachedrec, false);
            } else {
                $DB->update_record('block_mysites', $cachedrec);
            }

            return array($tablabels, $courselists, $backuplists, '');

        }



        /**
         * Make call to remote site webservice to collect enrollments
         * for given username.
         *
         * @param stdClass $site External site info container from our plugin config
         * @param string $username User for whom to fetch enrollments
         * @return array Array of objects keyed by external site course id.
         */
        private static function get_lists_for_site(stdClass $site, $username)
        {

            $lists = array('courses' => array(), 'backups' => array());

            $ws = new \webservice_rest_client(rtrim($site->url, '/') . "/webservice/rest/server.php", $site->token);
            $response = $ws->call('block_mysites_getlistsforusername', array('username' => $username));

            if (empty($response)) {
                return $lists;
            }

            // Have an XML doc to parse. Looks like the following,
            //
            // <RESPONSE>
            // <SINGLE>
            // <KEY name="courses">
            //   <MULTIPLE>
            //     <SINGLE>
            //       <KEY name="id"><VALUE>1</VALUE></KEY>
            //       <KEY name="crn"><VALUE>CRNVALUE</VALUE></KEY>
            //       <KEY name="shortname"><VALUE>Course shortname</VALUE></KEY>
            //       <KEY name="backupcap"><VALUE>1</VALUE></KEY>
            //       <KEY name="status"><VALUE>0</VALUE></KEY>
            //     </SINGLE>
            //   </MULTIPLE>
            // </KEY>
            // <KEY name="backups">
            //   <MULTIPLE>
            //     <SINGLE>
            //       <KEY name="filename"><VALUE>bob</VALUE></KEY>
            //       <KEY name="filesize"><VALUE>1</VALUE></KEY>
            //       <KEY name="pathnamehash"><VALUE>abcdef0123456789abcdef0123456789</VALUE></KEY>
            //       <KEY name="contenthash"><VALUE>abcdef0123456789abcdef0123456789</VALUE></KEY>
            //       <KEY name="timecreated"><VALUE>1521825466</VALUE></KEY>
            //     </SINGLE>
            //   </MULTIPLE>
            // </KEY>
            // </SINGLE>
            // </RESPONSE>
            //
            try {
                $docobj = new \SimpleXMLElement(trim($response));
            }
            catch (Exception $ex) {
                error_log('Failed to parse XML:' . $response);
                throw $ex;
            }

            if (!isset($docobj->SINGLE) || !isset($docobj->SINGLE->KEY)) {
                // Either no entries returned, or bad formatting
                return $lists;
            }

            // Iterate over the course SINGLE elements (records)
            foreach ($docobj->SINGLE->KEY[0]->MULTIPLE->SINGLE as $single) {
                // Iterate over the key/value pairs (properties)
                $item = new stdClass();
                foreach ($single->KEY as $key) {
                    $item->{$key->attributes()->name} = $key->VALUE->__toString();
                }
                // Remove any port component for now. Proper fix will
                // be for site config to have separate web service and
                // site urls.
                $urlparts = parse_url(rtrim($site->url, '/'));
                $urlpath =  empty($urlparts['path']) ? '' : $urlparts['path'];
                $item->url = "{$urlparts['scheme']}://{$urlparts['host']}{$urlpath}/course/view.php?id={$item->id}";
                $lists['courses'][$item->id] = $item;
                unset($item);
            }

            // Iterate over the backup SINGLE elements (records)
            foreach ($docobj->SINGLE->KEY[1]->MULTIPLE->SINGLE as $single) {
                // Iterate over the key/value pairs (properties)
                $item = new stdClass();
                foreach ($single->KEY as $key) {
                    $item->{$key->attributes()->name} = $key->VALUE->__toString();
                }
                $lists['backups'][$item->pathnamehash] = $item;
                unset($item);
            }

            return $lists;

        }


        /**
         * Send request to external site webservice
         *
         * @param int $action One of the block_mysites\lib::ACTION_X values
         * @param string $username Username for whom request is made
         * @param string $sendtositeid Id of external site
         * @param int $courseid Id of course on the external site
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function send_request_action($action, $username, $sendtositeid, $courseid)
        {

            $config = self::get_pluginconfig();

            // Determine TO which site we SEND the request
            $sendtosite = $config->sites[$sendtositeid];
            if (empty($sendtosite)) {
                return self::STATUS_REQUEST_FAILED;
            }

            $ws = new \webservice_rest_client(rtrim($sendtosite->url, '/') . "/webservice/rest/server.php", $sendtosite->token);
            $response = $ws->call("block_mysites_requestaction", array(
                'action'   => $action,
                'siteid'   => $config->thissiteid, // The site to which to RETURN the backup, i.e. THIS site
                'username' => $username,
                'courseid' => $courseid));

            if (empty($response)) {
                return self::STATUS_REQUEST_FAILED;
            }

            // Have an XML doc to parse. Looks like the following,
            //
            // <RESPONSE>
            //   <VALUE>1</VALUE>
            // </RESPONSE>
            //
            try {
                $docobj = new \SimpleXMLElement($response);
            } catch (Exception $ex) {
                return self::STATUS_REQUEST_FAILED;
            }
            if (!isset($docobj->VALUE)) {
                // Either no entries returned, or bad formatting
                return self::STATUS_REQUEST_FAILED;
            }

            return (int)$docobj->VALUE;

        }


        /**
         * Insert a backup request record in the queue
         *
         * @param string $siteid Site id of the requesting external site
         * @param string $username Username of user for whom request is made
         * @param int $courseid Course id with respect to this site
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function insert_backup_request($siteid, $username, $courseid)
        {
            global $DB;


            $config = self::get_pluginconfig();

            // Check user and course exist, then check user has
            // backup capability for that course
            $user = $DB->get_record('user', array('username' => $username));
            if (!$user) {
                return self::STATUS_REQUEST_FAILED;
            }

            $course = $DB->get_record('course', array('id' => $courseid));
            if (!$course) {
                return self::STATUS_REQUEST_FAILED;
            }

            $context = \context_course::instance($courseid);
            if (!$context) {
                return self::STATUS_REQUEST_FAILED;
            }

            if (!has_capability('moodle/backup:backupcourse', $context, $user)) {
                return self::STATUS_REQUEST_FAILED;
            }

            // Need to know where to return payload
            if (empty($config->sites[$siteid])) {
                return self::STATUS_REQUEST_FAILED;
            }

            $record = new \stdClass();
            $record->siteid = $siteid;
            $record->username = $user->username;
            $record->courseid = $course->id;
            $record->userid = $user->id;
            $record->timecreated =
            $record->timemodified = time();
            $record->status = self::STATUS_BACKUP_PENDING;

            try {
                // Might have dup alt key (siteid, username, courseid)
                if (!$DB->insert_record('block_mysites_queue', $record, false)) {
                    self::STATUS_REQUEST_FAILED;
                }
            }
            catch(Exception $exc) {
                error_log($exc->getMessage() . ':' . $exc->getTraceAsString());
                return self::STATUS_REQUEST_FAILED;
            }

            return self::STATUS_BACKUP_PENDING;

        }


        /**
         * Delete a pending backup request
         *
         * @param string $siteid Site id of the requesting external site
         * @param string $username Username of user for whom request is made
         * @param int $courseid Course id with respect to this site
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function cancel_backup_request($siteid, $username, $courseid)
        {
            global $DB;


            try {
                $DB->delete_records('block_mysites_queue', array('siteid' => $siteid, 'username' => $username, 'courseid' => $courseid, 'status' => self::STATUS_BACKUP_PENDING));
                // Check that it is gone, as status may have changed recently
                $record = $DB->get_record('block_mysites_queue', array('siteid' => $siteid, 'username' => $username, 'courseid' => $courseid));
                if (!$record) {
                    return self::STATUS_REQUEST_NONE;
                }
                // Not removed, so return current status
                return $record->status;
            }
            catch(Exception $ex) {
                return self::STATUS_REQUEST_FAILED;
            }

        }


        /**
         * Reset a request to previous
         *
         * @param string $siteid Site id of the requesting external site
         * @param string $username Username of user for whom request is made
         * @param int $courseid Course id with respect to this site
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function reset_backup_request($siteid, $username, $courseid)
        {
            global $DB;


            // Get request record and make sure at terminal state.
            $record = $DB->get_record('block_mysites_queue', array('siteid' => $siteid, 'username' => $username, 'courseid' => $courseid));
            if (!$record) {
                return self::STATUS_REQUEST_NONE;
            }

            try {
                switch($record->status) {

                    // Non-termianl states.
                    case self::STATUS_BACKUP_PENDING :
                    case self::STATUS_BACKUP_INPROGRESS :
                    case self::STATUS_TRANSFER_PENDING :
                    case self::STATUS_TRANSFER_INPROGRESS :
                    case self::STATUS_TRANSFER_COMPLETED :
                        return self::STATUS_REQUEST_FAILED;
                        break;

                    case self::STATUS_TRANSFER_FAILED :
                    case self::STATUS_TRANSFER_FILETOOBIG :
                        // Retry the file upload.
                        $record->status = self::STATUS_TRANSFER_PENDING;
                        $DB->update_record('block_mysites_queue', $record);
                        return $record->status;
                        break;

                    case self::STATUS_BACKUP_FAILED :
                        // Remove the rec, reverts to none.
                        $DB->delete_records('block_mysites_queue', array('siteid' => $siteid, 'username' => $username, 'courseid' => $courseid));
                        return self::STATUS_REQUEST_NONE;

                }
            } catch (Exception $ex) {
                return self::STATUS_REQUEST_FAILED;
            }

        }


        /**
         * Clear status of an already finished import
         *
         * @param int $action One of the block_mysites\lib::ACTION_X values
         * @param int $userid Id of user making request
         * @param string $siteid Id of external site
         * @param int $courseid Id of course on external site
         * @return int One of the block_mysites\lib::STATUS_X values
         */
        public static function clear_import($action, $userid, $siteid, $courseid)
        {
            global $DB;


            // Clear it
            try {
                $DB->delete_records('block_mysites_import', array('userid' => $userid, 'siteid' => $siteid, 'sitecourseid' => $courseid));
                return self::STATUS_REQUEST_NONE;
            } catch (Exception $ex) {
                return self::STATUS_REQUEST_FAILED;
            }

        }


        /**
         * Iterate over the queue records waiting for action and
         * move the process along
         *
         */
        public static function process_queue()
        {
            global $DB;


            list($sql, $params) = $DB->get_in_or_equal(array(
                self::STATUS_BACKUP_PENDING,
                self::STATUS_TRANSFER_PENDING,
                self::STATUS_TRANSFER_COMPLETED // But not yet made available
            ));
            $records = $DB->get_records_select('block_mysites_queue', "status {$sql}",
                $params, 'timemodified');

            if (!$records) {
                mtrace('No queue records need attention.');
                return;
            }

            foreach($records as $rec) {
                switch($rec->status) {
                    case self::STATUS_BACKUP_PENDING :
                        try {
                            self::backup_course($rec);
                        }
                        catch(Exception $ex) {
                            mtrace("Error: {$rec->siteid}/{$rec->username}/{$rec->courseid}: {$ex->getMessage()}");
                            self::set_job_status($rec->id, self::STATUS_BACKUP_FAILED);
                        }
                        break;
                    case self::STATUS_TRANSFER_PENDING :
                        try {
                            self::upload_course_backup($rec);
                        }
                        catch(Exception $ex) {
                            mtrace("Error: {$rec->siteid}/{$rec->username}/{$rec->courseid}: {$ex->getMessage()}");
                        }
                        break;
                    case self::STATUS_TRANSFER_COMPLETED :
                        try {
                            self::send_finish_upload($rec);
                        }
                        catch(Exception $ex) {
                            mtrace("Error: {$rec->siteid}/{$rec->username}/{$rec->courseid}: {$ex->getMessage()}");
                        }
                        break;
                }
            }

        }


        /**
         * Creates a course backup in specified user's backup files
         * area.
         *
         * @param stdClass $queuerec Queue record
         */
        public static function backup_course(stdClass $queuerec)
        {
            global $CFG, $DB;
            require_once("{$CFG->dirroot}/backup/util/includes/backup_includes.php");


            $config = self::get_pluginconfig();

            // Make sure return-to site is configured
            $returnsite = $config->sites[$queuerec->siteid];
            if (empty($returnsite)) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: return site not configured.");
                self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
                return;
            }

            // Make sure course is still here
            $course = $DB->get_record('course', array('id' => $queuerec->courseid));
            if (!$course) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: course not found.");
                self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
                return;
            }

            $controller = new \backup_controller(
                backup::TYPE_1COURSE, $queuerec->courseid, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $queuerec->userid
            );
            $plan = $controller->get_plan();

            // If users set to be included, attempt to exclude,
            // and bail if unable to do so. User may not have
            // capability to change users settings.
            $user_setting = $plan->get_setting('users');
            if ($user_setting->get_value()) {
                try {
                    $user_setting->set_value(false);
                } catch(\Exception $exc) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$exc->getMessage()}");
                    self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
                    $controller->destroy();
                    return;
                }
            }

            // Do likewise with logs.
            $logs_setting = $plan->get_setting('logs');
            if ($logs_setting->get_value()) {
                try {
                    $logs_setting->set_value(false);
                } catch(\Exception $exc) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$exc->getMessage()}");
                    self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
                    $controller->destroy();
                    return;
                }
            }

            // Adjust the output filename.
            $name_setting = $plan->get_setting('filename');
            try {
                $name_setting->set_value(backup_plan_dbops::get_default_backup_filename(
                    backup::FORMAT_MOODLE, backup::TYPE_1COURSE, $queuerec->courseid, false, false));
            } catch(\Exception $exc) {
                // Output filename did not validate, likely too
                // long (max is 90 chars).
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$exc->getMessage()}");
                self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
                $controller->destroy();
                return;
            }

            // This will also save and reload the controller.
            $controller->set_status(backup::STATUS_AWAITING);

            self::set_job_status($queuerec->id, self::STATUS_BACKUP_INPROGRESS);
            try {
                // Re-check capabilities for user
                backup_check::check_security($controller, false);
                // Build the compressed backup file, and put in user's
                // private backup files area
                $controller->execute_plan();
                $results = $controller->get_results();
                if(empty($results) || empty($results['backup_destination'])) {
                    $queuerec->status = self::STATUS_BACKUP_FAILED;
                } else {
                    // Should get a Moodle stored_file
                    $sf = $results['backup_destination'];
                    $queuerec->pathnamehash = $sf->get_pathnamehash();
                    // Determine if file size exceeds limit for
                    // that particular site
                    if ($returnsite->maxupload && $sf->get_filesize() > $returnsite->maxupload) {
                        $queuerec->status = self::STATUS_TRANSFER_FILETOOBIG;
                    } else {
                        $queuerec->status = self::STATUS_TRANSFER_PENDING;
                    }
                }
                // Update the rec's status and pathnamehash
                $DB->update_record('block_mysites_queue', $queuerec);
                mtrace("Backup: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$sf->get_filename()}, {$sf->get_contenthash()}");
            }
            catch(Exception $exc) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$exc->getMessage()}");
                self::set_job_status($queuerec->id, self::STATUS_BACKUP_FAILED);
            }
            finally {
                $controller->destroy();
            }

        }


        /**
         * Upload a course backup file to the requesting site
         *
         * @param stdClass $queuerec Queue record
         */
        private static function upload_course_backup(stdClass $queuerec)
        {
            global $CFG, $DB;


            $config = self::get_pluginconfig();

            // Make sure return-to site is configured
            $returnsite = $config->sites[$queuerec->siteid];
            if (empty($returnsite)) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: return site not configured.");
                self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                return;
            }

            // Get the file storage object from which we can get
            // a read-only file handle for curl
            $sf = get_file_storage()->get_file_by_hash($queuerec->pathnamehash);
            if (!$sf) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: invalid pathname hash {$queuerec->pathnamehash}.");
                self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                return;
            }

            if ($returnsite->maxupload > 0 && $sf->get_filesize() > $returnsite->maxupload) {
                self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FILETOOBIG);
                return;
            }

            self::set_job_status($queuerec->id, self::STATUS_TRANSFER_INPROGRESS);

            // Generally frowned upon, accessing the file directly in
            // the repository, but necessary since CURLFile will not
            // accept a file handle resource.
            $contenthash = $sf->get_contenthash();
            $repofilepath = substr($contenthash, 0, 2) . "/"
                          . substr($contenthash, 2, 2) . "/"
                          . $contenthash;

            // Set up the curl call and get fetch draft area id
            $ch = curl_init("{$returnsite->url}/webservice/upload.php?token={$returnsite->token}&filepath=/&filearea=draft");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' =>
                new \CURLFile("{$CFG->dataroot}/filedir/{$repofilepath}", null, $sf->get_filename()))
            );

            try {

                // Expecting a JSON formatted array of one object (success), or
                // a single object (exception). Might have additional HTML
                // markup if Apache/PHP balking due to file/post size limit
                $curlresponse = curl_exec($ch);
                $curlerror = curl_error($ch);

                if (empty($curlresponse) || $curlerror) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$curlerror}");
                    self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                    return;
                }

                // If Apache issue (e.g. access error, POST size) will have HTML
                // markup, not just formatted JSON
                $response = json_decode($curlresponse, true);
                $jsonerror = json_last_error();

                if (empty($response) || !is_array($response) || $jsonerror != JSON_ERROR_NONE) {
                    if (preg_match('/post content-length of \d+ bytes exceeds/im', $curlresponse)) {
                        self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FILETOOBIG);
                    } else {
                        // Otherwise, don't know where to find payload
                        self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                        mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: invalid response format: {$curlresponse}");
                    }
                    return;
                }

                // If first-pass Moodle exception (e.g. missing or bad token),
                // single JSON object returned
                if (isset($response['error'])) {
                    self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$response['error']}--{$response['debuginfo']}");
                    return;
                }

                // If second-pass Moodle error (e.g. file size exceeds Moodle
                // limit, but not PHP's), error info formed in response array
                if (isset($response[0]['error'])) {
                    if ($response[0]['errortype'] == 'fileoversized') {
                        self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FILETOOBIG);
                    } else {
                        self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                    }
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$response[0]['error']}");
                    return;
                }

                // Should have the stored_file information in array in array
                if (!isset($response[0]['itemid']) || empty($response[0]['itemid'])) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: itemid missing.");
                    self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                    return;
                }

                $itemid = $response[0]['itemid'];

                $queuerec->returnitemid = $itemid;
                $queuerec->status = self::STATUS_TRANSFER_COMPLETED;
                $DB->update_record('block_mysites_queue', $queuerec);

                mtrace("Upload: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: itemid {$itemid}.");

                self::send_finish_upload($queuerec);

            }
            catch(Exception $ex) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$ex->getMessage()}.");
                self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
            }
            finally {
                if ($ch) { curl_close($ch); }
            }

        }


        /**
         * Signal the external site to move the uploaded file from the
         * web service's draft area into the specified user's backup
         * files area.
         *
         * @param stdClass $queuerec
         */
        private static function send_finish_upload(stdClass $queuerec)
        {
            global $DB;


            $config = self::get_pluginconfig();

            $returnsite = $config->sites[$queuerec->siteid];
            if (empty($returnsite)) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: return site not configured.");
                self::set_job_status($queuerec->id, self::STATUS_TRANSFER_FAILED);
                return;
            }

            // File is in draft area on return site, need to call
            // the mysites web service and provide the itemid to
            // finalize the transfer
            $wsurl = trim(rtrim($returnsite->url, '/')) . "/webservice/rest/server.php";
            $ws = new \webservice_rest_client($wsurl, $returnsite->token);

            try {

                $response = $ws->call("block_mysites_finishupload", array(
                    'siteid' => $config->thissiteid, 'username' => $queuerec->username,
                    'courseid' => $queuerec->courseid, 'itemid' => $queuerec->returnitemid));
                if (empty($response)) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: web svc call failed.");
                    return;
                }

                try {
                    $docobj = new \SimpleXMLElement($response);
                } catch (Exception $ex) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$ex->getMessage()}.");
                    return;
                }

                if (isset($docobj->ERRORCODE)) {
                    // Web service returned an exception
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$docobj->ERRORCODE}, {$docobj->MESSAGE}.");
                    return;
                }
                if (!isset($docobj->VALUE)) {
                    // Either no entries returned, or bad formatting
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: response value missing.");
                    return;
                }

                $status = (int)$docobj->VALUE;

                // Failed for some reason, leave status as is for retry
                if ($status != self::STATUS_BACKUP_AVAILABLE) {
                    mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: finish upload response {$status}.");
                    return;
                }

                // Success, clean up this side
                get_file_storage()->get_file_by_hash($queuerec->pathnamehash)->delete();
                $DB->delete_records('block_mysites_queue', array('id' => $queuerec->id));

                mtrace("Available: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}.");

            }
            catch(Exception $ex) {
                mtrace("Error: {$queuerec->siteid}/{$queuerec->username}/{$queuerec->courseid}: {$ex->getMessage()}.");
            }

        }


        /**
         * Move the uploaded file from this webservice's draft area
         * to the specified user's backup files area, and create an
         * import record for it.
         *
         * @param string $siteid External site id
         * @param string $username Username of user for whom backup is made
         * @param int $courseid Id of course on the external site
         * @param int $itemid Draft item id
         * @return int One of the \block_mysites\lib::STATUS_X values
         */
        public static function finish_upload($siteid, $username, $courseid, $itemid)
        {
            global $DB, $USER;


            // For clarity, the web service user, controlled
            // by auth token supplied at invocation
            $wsuser = $USER;

            // The user to whom we're making the backup available
            $touser = $DB->get_record('user', array('username' => $username));
            if (!$touser) {
                return self::STATUS_REQUEST_FAILED;
            }

            $tousercontext = \context_user::instance($touser->id, IGNORE_MISSING);
            if (!$tousercontext) {
                return self::STATUS_REQUEST_FAILED;
            }

            // Want to get list of files in draft area BEFORE calling
            // file_save_draft_area_files() because afterwards they'll
            // be merged with the other files already in user/backup.
            $files = get_file_storage()->get_directory_files(\context_user::instance($wsuser->id)->id, 'user', 'draft', $itemid, '/', false, false);
            if (!$files) {
                return self::STATUS_REQUEST_FAILED;
            }

            // Should be one file per draft area
            foreach($files as $file) {

                // Should never be the case, but...
                if ($file->is_directory()) {
                    continue;
                }

                // Add an import entry
                $importrec = new \stdClass();
                $importrec->userid = $touser->id;
                $importrec->siteid = $siteid;
                $importrec->sitecourseid = $courseid;
                $importrec->filename = $file->get_filename();
                $importrec->timecreated = time();

                $DB->insert_record('block_mysites_import', $importrec, false);

            }

            // Move the file to its final destination
            file_merge_files_from_draft_area_into_filearea($itemid, $tousercontext->id, 'user', 'backup', 0);

            return self::STATUS_BACKUP_AVAILABLE;

        }


        private static function set_job_status($queuerecid, $status)
        {
            global $DB;


            $DB->set_field('block_mysites_queue', 'status', $status,
                array('id' => $queuerecid));

        }

    } // class
