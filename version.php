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


    $plugin             = new stdClass();

    $plugin->component  = 'block_mysites';
    $plugin->version    = 2018052000;
    $plugin->requires   = 2014051200;
    $plugin->release    = "1.0.0 (Build 2018040100)";
    $plugin->cron       = 0;
    $plugin->maturity   = MATURITY_STABLE;
