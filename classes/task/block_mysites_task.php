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

    namespace block_mysites\task;
    use block_mysites\lib;
    use core\task\scheduled_task;
    use Exception;

    defined('MOODLE_INTERNAL') || die();



    class block_mysites_task extends scheduled_task
    {

        /**
         * {@inheritDoc}
         * @see \core\task\scheduled_task::get_name()
         */
        public function get_name()
        {
            return get_string('taskname', 'block_mysites');
        }


        /**
         * {@inheritDoc}
         * @see \core\task\task_base::execute()
         */
        public function execute()
        {

            // Not configured, nothing to do.
            if (!lib::plugin_configured()) {
                return;
            }

            // Work the queue.
            try { lib::process_queue(); }
            catch(Exception $ex) { /*Squelch it. */ }

            // Purge the cache.
            try { lib::clear_stale_caches(); }
            catch(Exception $ex) { /*Squelch it. */ }

        }

    }
