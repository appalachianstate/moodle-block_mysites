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
     * @copyright   (c) 2017 Appalachian State Universtiy, Boone, NC
     * @license     GNU General Public License version 3
     * @package     block_mysites
     */

    use block_mysites\output\renderable;

    defined('MOODLE_INTERNAL') || die();



    /**
     * Block plugin
     */
    class block_mysites extends block_base
    {


        /**
         * {@inheritDoc}
         * @see block_base::applicable_formats()
         */
        public function applicable_formats()
        {
            return array('my' => true);
        }


        /**
         * {@inheritDoc}
         * @see block_base::get_aria_role()
         */
        public function get_aria_role()
        {
            return 'complementary';
        }


        /**
         * {@inheritDoc}
         * @see block_base::has_config()
         */
        public function has_config()
        {
            return true;
        }


        /**
         * Called by the parent class constructor
         */
        public function init()
        {

            $this->content_type = BLOCK_TYPE_TREE;

            $title = get_config('block_mysites', 'blocktitle');
            if (empty($title)) {
                $title = get_string('pluginname', 'block_mysites');
            }
            $this->title = $title;

        }


        /**
         * {@inheritDoc}
         * @see block_base::get_required_javascript()
         */
        public function get_required_javascript()
        {

            // Want the Tree module set up using a page requirement,
            // but the ajax loader will load by using a data- tag
            // attribute (data-ajax-loader) emitted in the renderer
            parent::get_required_javascript();
            $this->page->requires->js_call_amd('block_mysites/module', 'init', array('blockinstanceid' => $this->instance->id));

        }


        /**
         * {@inheritDoc}
         * @see block_base::get_content()
         */
        public function get_content()
        {

            // This method called initially only to see if content
            // exists, then a second time to actually emit it.
            if ($this->content !== null) {
                return $this->content;
            }

            // Renderer will create markup for both the content's text
            // and footer properties
            $this->content = $this->page->get_renderer('block_mysites')
                ->render(new renderable($this->instance->id));

            return $this->content;

        }

    }
