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

    namespace block_mysites\output;
    use block_mysites\lib;

    defined('MOODLE_INTERNAL') || die();



    /**
     * Data container class for mysites block used with a custom
     * plugin renderer class
     */
    class renderable implements \renderable
    {

        /**
         * @var string Error message occurring from data collection.
         */
        public $errormsg = '';

        /**
         * @var integer Block instance id value.
         */
        public $blockinstanceid = 0;

        /**
         * @var integer Selected site tab index.
         */
        public $siteindex = 1;

        /**
         * @var integer Selected course-or-backup button index.
         */
        public $corbindex = 1;

        /**
         * @var integer Selected page index.
         */
        public $pageindex = 1;

        /**
         * @var array of array of stdClass objects, keyed by site id
         */
        public $courselists = array();

        /**
         * @var array of array of stdClass objects, keyed by site id
         */
        public $backuplists = array();

        /**
         * @var array of string, labels for tabs
         */
        public $tablabels = array();


        /**
         * Constructor
         *
         * @param int $blockinstanceid Block instance id
         * @param bool $refresh Whether to clear the cache or not
         */
        public function __construct($blockinstanceid, $refresh = false, $siteindex = 1, $corbindex = 1, $pageindex = 1)
        {
            global $USER;


            // Want these later for the renderer
            $this->blockinstanceid = $blockinstanceid;
            $this->siteindex = $siteindex;
            $this->corbindex = $corbindex;
            $this->pageindex = $pageindex;

            // Fetch the payload
            list($this->tablabels, $this->courselists, $this->backuplists, $this->errormsg)
                = lib::get_lists($USER, $refresh);

        }

    }
