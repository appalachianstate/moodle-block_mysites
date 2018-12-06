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
    use core\output\notification;
    use plugin_renderer_base;
    use html_writer;
    use stdClass;

    defined('MOODLE_INTERNAL') || die();



    /**
     * Renderer for MySites block plugin.
     */
    class renderer extends plugin_renderer_base
    {

        const PAGE_SIZE = 6;

        private static $progresspcts = array(
            lib::STATUS_REQUEST_NONE            => 0,
            lib::STATUS_REQUEST_FAILED          => 0,
            lib::STATUS_BACKUP_PENDING          => 17,
            lib::STATUS_BACKUP_INPROGRESS       => 34,
            lib::STATUS_BACKUP_FAILED           => 34,
            lib::STATUS_TRANSFER_PENDING        => 50,
            lib::STATUS_TRANSFER_INPROGRESS     => 67,
            lib::STATUS_TRANSFER_FAILED         => 67,
            lib::STATUS_TRANSFER_FILETOOBIG     => 67,
            lib::STATUS_TRANSFER_COMPLETED      => 84,
            lib::STATUS_BACKUP_AVAILABLE        => 100
        );

        private static $progressactions = array(
            lib::STATUS_REQUEST_NONE            => lib::ACTION_BACKUP,
            lib::STATUS_REQUEST_FAILED          => lib::ACTION_RESET,
            lib::STATUS_BACKUP_PENDING          => lib::ACTION_CANCEL,
            lib::STATUS_BACKUP_INPROGRESS       => '',
            lib::STATUS_BACKUP_FAILED           => lib::ACTION_RESET,
            lib::STATUS_TRANSFER_PENDING        => '',
            lib::STATUS_TRANSFER_INPROGRESS     => '',
            lib::STATUS_TRANSFER_FAILED         => lib::ACTION_RESET,
            lib::STATUS_TRANSFER_FILETOOBIG     => lib::ACTION_RESET,
            lib::STATUS_TRANSFER_COMPLETED      => '',
            lib::STATUS_BACKUP_AVAILABLE        => lib::ACTION_RESTORE
        );


        private static $progressrunind = array(
            lib::STATUS_REQUEST_NONE            => false,
            lib::STATUS_REQUEST_FAILED          => false,
            lib::STATUS_BACKUP_PENDING          => true,
            lib::STATUS_BACKUP_INPROGRESS       => true,
            lib::STATUS_BACKUP_FAILED           => false,
            lib::STATUS_TRANSFER_PENDING        => true,
            lib::STATUS_TRANSFER_INPROGRESS     => true,
            lib::STATUS_TRANSFER_FAILED         => false,
            lib::STATUS_TRANSFER_FILETOOBIG     => false,
            lib::STATUS_TRANSFER_COMPLETED      => true,
            lib::STATUS_BACKUP_AVAILABLE        => false
        );


        private static $progresserror = array(
            lib::STATUS_REQUEST_NONE            => false,
            lib::STATUS_REQUEST_FAILED          => true,
            lib::STATUS_BACKUP_PENDING          => false,
            lib::STATUS_BACKUP_INPROGRESS       => false,
            lib::STATUS_BACKUP_FAILED           => true,
            lib::STATUS_TRANSFER_PENDING        => false,
            lib::STATUS_TRANSFER_INPROGRESS     => false,
            lib::STATUS_TRANSFER_FAILED         => true,
            lib::STATUS_TRANSFER_FILETOOBIG     => true,
            lib::STATUS_TRANSFER_COMPLETED      => false,
            lib::STATUS_BACKUP_AVAILABLE        => false
        );



        /**
         * Custom rendering method for block_mysites, called from
         * plugin_renderer_base::render() method, emits markup for
         * use with the Bootstrap tabbed navigation. Return value
         * assigned to block->content property.
         *
         * @param  \block_mysites\output\renderable $widget Data container object
         * @return \stdClass Block content object with text and footer properties
         */
        protected function render_renderable(renderable $widget)
        {

            $content = new stdClass();
            $content->footer = $this->render_content_footer($widget);

            if (!empty($widget->errormsg)) {
                $content->text = $this->output->notification($widget->errormsg, notification::NOTIFY_ERROR);
            } else {
                $content->text = $this->render_content_text($widget);
            }

            return $content;

        }


        /**
         * Renders the widget for the user dashboard showing list of
         * courses on external sites to which user has access.
         *
         * @param block_mysites\output\renderable $widget Data container sites and course lists
         * @return string HTML markup for block content
         */
        private function render_content_text(renderable $widget)
        {

            $attribs = array('id' => "inst{$widget->blockinstanceid}-content");
            if ($widget->deferload) {
                $attribs['data-refresh'] = '1';
            }

            return \html_writer::start_div('', $attribs)
                 . $this->render_site_tabs($widget)
                 . $this->render_site_panels($widget)
                 . \html_writer::end_div(); // id

        }


        /**
         * Generate markup for the tab navs, one tab per site
         *
         * @param block_mysites\output\renderable $widget Data container sites and course lists
         * @return string HTML markup
         */
        private function render_site_tabs(renderable $widget)
        {

            $config = lib::get_pluginconfig();

            $tabnavs = '';
            $siteindex = 0;

            foreach ($widget->tablabels as $siteid => $tablabel) {
                // If no courses or backups no reason to
                // display this tab label
                if (   count($widget->courselists[$siteid]) == 0
                    && (   count($widget->backuplists[$siteid]) == 0
                        || (    count($widget->backuplists[$siteid]) > 0
                             && empty($config->showbackups)
                           )
                        )
                   ) {
                    continue;
                }

                $siteindex++;
                $classattrib = 'nav-link' . ($siteindex == $widget->siteindex ? ' active' : '');
                $tabnavs .= \html_writer::start_tag('li', array('class' => 'nav-item'))
                         .  \html_writer::link("#inst{$widget->blockinstanceid}-tab-{$siteindex}", "{$tablabel}",
                                array('class' => $classattrib, 'role' => 'tab', 'data-toggle' => 'tab', 'data-site-index' => $siteindex))
                         .  \html_writer::end_tag('li');

            }

            return \html_writer::start_tag('ul', array('id' => "inst{$widget->blockinstanceid}-tablist", 'class' => 'nav nav-tabs m-b-1', 'role' => 'tablist'))
                 . $tabnavs
                 . \html_writer::end_tag('ul');

        }


        /**
         * Render the tab pane section of block content
         *
         * @param block_mysites\output\renderable $widget Data container sites and course lists
         * @return string HTML markup
         */
        private function render_site_panels(renderable $widget)
        {

            $config = lib::get_pluginconfig();

            $panels = '';
            $siteindex = 0;

            // For each site create a single panel which contains
            // a paged list of courses
            $siteids = array_keys($widget->tablabels);
            foreach($siteids as $siteid) {
                // If no courses or backups no reason to
                // display this panel
                if (   count($widget->courselists[$siteid]) == 0
                    && (   count($widget->backuplists[$siteid]) == 0
                        || (    count($widget->backuplists[$siteid]) > 0
                             && empty($config->showbackups)
                           )
                        )
                   ) {
                    continue;
                }

                $siteindex++;

                $panelcontent = \html_writer::start_div('tab-pane fade ' . ($siteindex == $widget->siteindex ? ' active in' : ''), array('id' => "inst{$widget->blockinstanceid}-tab-{$siteindex}"));

                if (!empty($config->showbackups)) {

                    $coursesbtnattribs = array('class' => 'btn btn-default', 'data-toggle' => 'tab', 'data-corb-index' => '1');
                    $coursesbtnattribs['class'] .= ($siteindex == $widget->siteindex) ? ($widget->corbindex == 1 ? ' active' : '') : ' active';
                    $backupsbtnattribs = array('class' => 'btn btn-default', 'data-toggle' => 'tab', 'data-corb-index' => '2');
                    $backupsbtnattribs['class'] .= ($siteindex == $widget->siteindex) ? ($widget->corbindex == 2 ? ' active' : '') : '';

                    // Courses + Backups button pair
                    $panelcontent .= \html_writer::start_div('text-xs-center text-center');
                    $panelcontent .= \html_writer::start_div('btn-group m-b-1', array('role' => 'group', 'data-toggle' => 'btns'));
                    $panelcontent .= \html_writer::link("#inst{$widget->blockinstanceid}-tab-{$siteindex}-courses", get_string('courses') . " (" . count($widget->courselists[$siteid]) . ")",
                                      $coursesbtnattribs);
                    $panelcontent .= \html_writer::link("#inst{$widget->blockinstanceid}-tab-{$siteindex}-backups", get_string('backups', 'admin')  . " (" . count($widget->backuplists[$siteid]) . ")",
                                      $backupsbtnattribs);
                    $panelcontent .= \html_writer::end_div();
                    $panelcontent .= \html_writer::end_div();
                    // End Courses + Backups button pair

                    $panelcontent .= \html_writer::start_div('tab-content content-centered');

                    $coursesdivclass  = 'tab-pane fade';
                    $coursesdivclass .= ($siteindex == $widget->siteindex) ? ($widget->corbindex == 1 ? ' active in' : '') : ' active in';
                    $coursespage = ($siteindex == $widget->siteindex) ? ($widget->corbindex == 1 ? $widget->pageindex : 1) : 1;

                    $panelcontent .= \html_writer::start_div($coursesdivclass, array('id' => "inst{$widget->blockinstanceid}-tab-{$siteindex}-courses"));
                    $panelcontent .= $this->render_course_pages($siteid, $widget->courselists[$siteid], $coursespage, $config->sites[$siteid]->cansendreq);
                    $panelcontent .= \html_writer::end_div();

                    $backupsdivclass  = 'tab-pane fade';
                    $backupsdivclass .= ($siteindex == $widget->siteindex) ? ($widget->corbindex == 2 ? ' active in' : '') : '';
                    $backupspage = ($siteindex == $widget->siteindex) ? ($widget->corbindex == 2 ? $widget->pageindex : 1) : 1;

                    $panelcontent .= \html_writer::start_div($backupsdivclass, array('id' => "inst{$widget->blockinstanceid}-tab-{$siteindex}-backups"));
                    $panelcontent .= $this->render_backup_pages($siteid, $widget->backuplists[$siteid], $backupspage);
                    $panelcontent .= \html_writer::end_div();

                    $panelcontent .= \html_writer::end_div(); // tab-content

                } else {

                    $coursespage = ($siteindex == $widget->siteindex) ? $widget->pageindex : 1;

                    $panelcontent .= \html_writer::start_div('', array('id' => "inst{$widget->blockinstanceid}-tab-{$siteindex}-courses"));
                    $panelcontent .= $this->render_course_pages($siteid, $widget->courselists[$siteid], $coursespage, $config->sites[$siteid]->cansendreq);
                    $panelcontent .= \html_writer::end_div();

                }

                $panelcontent .= \html_writer::end_div(); // tab-pane

                $panels .= $panelcontent;
            } // foreach($widget->courselists ...


            return \html_writer::start_div('tab-content content-centered')
                 . $panels
                 . \html_writer::end_div();

        }


        private function render_course_pages($siteid, $courselist, $selectedpage, $cansendreq = true)
        {

            $coursecount = count($courselist);
            $pagecount = intdiv($coursecount, self::PAGE_SIZE) + ($coursecount % self::PAGE_SIZE ? 1 : 0);
            $pagedcontent = '';
            for ($page = 1; $page <= $pagecount; $page++) {

                $pagecontent = \html_writer::start_div($page == $selectedpage ? '' : ' hidden', array('data-region' => 'paging-content-item', 'data-page' => $page));

                for($i = ($page - 1) * self::PAGE_SIZE; $i <= (($page - 1) * self::PAGE_SIZE) + self::PAGE_SIZE - 1; $i++) {
                    if (empty($courselist[$i])) { break; }

                    if ($i % 2 == 0) {
                        $pagecontent .= \html_writer::start_div('row');
                    }

                    $pagecontent .= \html_writer::start_div('col-md-6');
                    $pagecontent .= \html_writer::start_div('card m-b-1');
                    $pagecontent .= \html_writer::start_div('card-block');

                    // Small
                    $pagecontent .= \html_writer::start_div('hidden-sm-up');

                    // Shortname (link)
                    $pagecontent .= \html_writer::start_tag('h4', array('class' => 'h5'));
                    $pagecontent .= \html_writer::link($courselist[$i]->url, htmlspecialchars($courselist[$i]->shortname), ($courselist[$i]->visible) ? null : array('class' => 'dimmed'));
                    $pagecontent .= \html_writer::end_tag('h4');

                    $pagecontent .= \html_writer::end_div(); // hidden-sm-up
                    // End small

                    // Large
                    $pagecontent .= \html_writer::start_div('hidden-xs-down');

                    $pagecontent .= \html_writer::start_div('media');

                    // Image
                    $pagecontent .= \html_writer::start_div('media-left no-progress');
                    $pagecontent .= $this->output->render(new \pix_icon('/i/course', get_string('course')));
                    $pagecontent .= \html_writer::end_div(); // media-left

                    $pagecontent .= \html_writer::start_div('media-body');
                    $pagecontent .= \html_writer::start_div('media-object m-b-1');

                    // Shortname (link)
                    $pagecontent .= \html_writer::start_tag('h4', array('class' => 'h5'));
                    $pagecontent .= \html_writer::link($courselist[$i]->url, htmlspecialchars($courselist[$i]->shortname), ($courselist[$i]->visible) ? null : array('class' => 'dimmed'));
                    $pagecontent .= \html_writer::end_tag('h4');

                    // CRN
                    $pagecontent .= \html_writer::start_tag('p', array('class' => 'text-muted'));
                    $pagecontent .= empty($courselist[$i]->crn) ? '&nbsp' : htmlspecialchars($courselist[$i]->crn);
                    $pagecontent .= \html_writer::end_tag('p');

                    $pagecontent .= \html_writer::end_div(''); // media-object
                    $pagecontent .= \html_writer::end_div(); // media-body

                    $pagecontent .= \html_writer::end_div(); // media

                    // If site configured such we do not send requests
                    // then omit this chunk of markup
                    if ($cansendreq) {

                        // Status
                        $pagecontent .= \html_writer::start_div('status-wrapper');
                        if ($courselist[$i]->backupcap) {
                            $filename = empty($courselist[$i]->filename) ? '' : $courselist[$i]->filename;
                            $pagecontent .= self::render_course_status_div($courselist[$i]->status, $siteid, $courselist[$i]->id, $filename);
                        }
                        $pagecontent .= \html_writer::end_div(); // status-wrapper

                    } // if ($cansendreq)

                    $pagecontent .= \html_writer::end_div(); // hidden-xs-down
                    // End large

                    $pagecontent .= \html_writer::end_div(); // card-block
                    $pagecontent .= \html_writer::end_div(); // card m-b-1 ...
                    $pagecontent .= \html_writer::end_div(); // col-md-6

                    if ($i % 2 == 1 || $i == ($coursecount - 1)) {
                        $pagecontent .= \html_writer::end_div(); // row
                    }

                } // for($i ...

                $pagecontent .= \html_writer::end_div(); // page

                $pagedcontent .= $pagecontent;
            } // for ($page ...

            return \html_writer::start_div('', array('data-region' => 'paging-content'))
                 . $pagedcontent
                 . ($pagecount > 1 ? $this->render_page_bar($pagecount, $selectedpage) : '')
                 . \html_writer::end_div(); // paging-content

        }


        private function render_backup_pages($siteid, $backuplist, $selectedpage)
        {

            $backupcount = count($backuplist);
            $pagecount = intdiv($backupcount, self::PAGE_SIZE) + ($backupcount % self::PAGE_SIZE ? 1 : 0);
            $pagedcontent = '';
            for ($page = 1; $page <= $pagecount; $page++) {

                $pagecontent = \html_writer::start_div($page == $selectedpage ? '' : ' hidden', array('data-region' => 'paging-content-item', 'data-page' => $page));

                for($i = ($page - 1) * self::PAGE_SIZE; $i <= (($page - 1) * self::PAGE_SIZE) + self::PAGE_SIZE - 1; $i++) {
                    if (empty($backuplist[$i])) { break; }

                    if ($i % 2 == 0) {
                        $pagecontent .= \html_writer::start_div('row');
                    }

                    $pagecontent .= \html_writer::start_div('col-md-6');
                    $pagecontent .= \html_writer::start_div('card m-b-1');
                    $pagecontent .= \html_writer::start_div('card-block');

                    // Small
                    $pagecontent .= \html_writer::start_div('hidden-sm-up');

                    $pagecontent .= \html_writer::start_tag('h4', array('class' => 'h5'));
                    $pagecontent .= \html_writer::link($backuplist[$i]->downloadurl, htmlspecialchars($backuplist[$i]->filename), array());
                    $pagecontent .= \html_writer::end_tag('h4');

                    $pagecontent .= \html_writer::end_div();
                    // End small

                    // Large
                    $pagecontent .= \html_writer::start_div('hidden-xs-down');

                    $pagecontent .= \html_writer::start_div('media');

                    // Image
                    $pagecontent .= \html_writer::start_div('media-left no-progress');
                    $pagecontent .= $this->output->render(new \pix_icon('/i/backup', get_string('backup')));
                    $pagecontent .= \html_writer::end_div(); // media-left

                    $pagecontent .= \html_writer::start_div('media-body');

                    // Shortname (link)
                    $pagecontent .= \html_writer::start_tag('h4', array('class' => 'h5'));
                    $pagecontent .= \html_writer::link($backuplist[$i]->downloadurl, htmlspecialchars($backuplist[$i]->filename), array());
                    $pagecontent .= \html_writer::end_tag('h4');
                    // File attributes
                    $pagecontent .= \html_writer::start_tag('p', array('class' => 'text-muted'));
                    $pagecontent .= get_string('size') . '&nbsp;' . display_size($backuplist[$i]->filesize)
                                 .  ',&nbsp;' . get_string('datecreated', 'repository') . '&nbsp;' . userdate($backuplist[$i]->timecreated);
                    $pagecontent .= \html_writer::end_tag('p');

                    $pagecontent .= \html_writer::end_div(); // media-body

                    $pagecontent .= \html_writer::end_div(); // media

                    $pagecontent .= \html_writer::end_div();
                    // End large

                    $pagecontent .= \html_writer::end_div(); // card-block
                    $pagecontent .= \html_writer::end_div(); // card m-b-1 ...
                    $pagecontent .= \html_writer::end_div(); // col-md-4

                    if ($i % 2 == 1 || $i == ($backupcount - 1)) {
                        $pagecontent .= \html_writer::end_div(); // row
                    }

                } // for($i ...

                $pagecontent .= \html_writer::end_div(); // row

                $pagedcontent .= $pagecontent;
            } // for ($page ...

            return \html_writer::start_div('', array('data-region' => 'paging-content'))
                 . $pagedcontent
                 . ($pagecount > 1 ? $this->render_page_bar($pagecount, $selectedpage) : '')
                 . \html_writer::end_div(); // paging-content

        }


        private function render_page_bar($pagecount, $selectedpage)
        {

            $pagebar  = \html_writer::start_div('text-xs-center');
            $pagebar .= \html_writer::start_tag('nav', array('data-region' => 'page-bar', 'data-page-count' => $pagecount, 'data-page-number' => $selectedpage));
            $pagebar .= \html_writer::start_tag('ul', array('class' => 'pagination'));

            $pagebar .= \html_writer::start_tag('li', array('class' => 'page-item' . ($pagecount == 1 ? ' disabled' : ''), 'data-page-number' => 'first'));
            $pagebar .= \html_writer::link('#', '&laquo;', array('class' => 'page-link'));
            $pagebar .= \html_writer::end_tag('li');

            for($i = 1; $i <= $pagecount; $i++) {
                $pagebar .= \html_writer::start_tag('li', array('class' => 'page-item' . ($i == $selectedpage ? ' active' : ''), 'data-page-number' => $i));
                $pagebar .= \html_writer::link('#', $i, array('class' => 'page-link'));
                $pagebar .= \html_writer::end_tag('li');
            }

            $pagebar .= \html_writer::start_tag('li', array('class' => 'page-item' . ($pagecount == 1 ? ' disabled' : ''), 'data-page-number' => 'last'));
            $pagebar .= \html_writer::link('#', '&raquo;', array('class' => 'page-link'));
            $pagebar .= \html_writer::end_tag('li');

            $pagebar .= \html_writer::end_tag('ul');
            $pagebar .= \html_writer::end_tag('nav');
            $pagebar .= \html_writer::end_div(); // .text-xs-center

            return $pagebar;

        }


        /**
         * Render just a portion of card-block, showing current status
         *
         * @param int $status
         * @param string $siteid
         * @param int $courseid
         * @param string $filename
         * @return string Rendered HTML
         */
        public function render_course_status_div($status, $siteid, $courseid, $filename = '')
        {

            $running = self::$progressrunind[$status];
            $errcondition = self::$progresserror[$status];
            $percentdone = self::$progresspcts[$status];
            $action = self::$progressactions[$status];


            $divcontent  = \html_writer::start_div('status-container' . ($running ? ' running' : ''));

            $divcontent .= \html_writer::start_div('status-right');

            // Status
            $statusstr = get_string("status_{$status}", 'block_mysites');
            $divcontent .= \html_writer::start_tag('span', array('class' => 'status'));
            $divcontent .= (empty($statusstr) ? '&nbsp;' : $statusstr . '&nbsp;');
            $divcontent .= \html_writer::end_tag('span');

            // Clear button
            if ($status == lib::STATUS_BACKUP_AVAILABLE && is_siteadmin(\core\session\manager::get_realuser())) {
                $buttonattribs = array('autocomplete' => 'off', 'type' => 'button', 'class' => 'btn btn-secondary btn-sm', 'data-action' => 'clear', 'data-siteid' => $siteid, 'data-courseid' => $courseid);
                $divcontent .= \html_writer::start_tag('button', $buttonattribs);
                $divcontent .= get_string('reset', 'block_mysites');
                $divcontent .= \html_writer::end_tag('button');
            }

            // Progress
            if ($percentdone > 0) {
                $divcontent .= \html_writer::start_tag('progress', array('class' => 'progress' . ($errcondition ? ' progress-danger' : ($running ? '' : ' progress-success')), 'value' => $percentdone, 'max' => '100', 'role' => 'progressbar', 'aria-valuenow' => $percentdone, 'aria-valuemin' => '0', 'aria-valuemax' => '100'));
                $divcontent .= \html_writer::end_tag('progress');
            }

            $divcontent .= \html_writer::end_div(); // status-right

            $divcontent .= \html_writer::start_div('status-left');

            // Action
            if (!empty($action)) {
                $buttonattribs = array('autocomplete' => 'off', 'type' => 'button', 'class' => 'btn btn-secondary', 'data-action' => $action, 'data-siteid' => $siteid, 'data-courseid' => $courseid);
                if ($status == lib::STATUS_BACKUP_AVAILABLE) {
                    $buttonattribs['data-filename'] = $filename;
                }
                $divcontent .= \html_writer::start_tag('button', $buttonattribs);
                $divcontent .= get_string($action, 'block_mysites');
                $divcontent .= \html_writer::end_tag('button');
            } else {
                $divcontent .= '&nbsp;';
            }

            $divcontent .= \html_writer::end_div(); // status-left

            $divcontent .= \html_writer::end_div(); // status-container

            return $divcontent;

        }


        /**
         * Render the modal dialog form markup to restore to a
         * selectable course.
         *
         * @param array $courserecs Courses in which user has restore capability (this site)
         * @param int $usercontextid User's context id value
         * @return string HTML markup for modal dialog form
         */
        public function render_course_select($courserecs, $usercontextid)
        {
            global $CFG;


            $url = "{$CFG->wwwroot}/backup/restorefile.php";

            $form  = \html_writer::start_div();
            $form .= \html_writer::start_tag('form', array('name' => 'form', 'action' => $url, 'method' => 'GET', 'class' => 'mform'));

            $form .= \html_writer::start_tag('input', array('name' => 'action', 'type' => 'hidden', 'value' => 'choosebackupfile'));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'component', 'type' => 'hidden', 'value' => 'user'));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'filearea', 'type' => 'hidden', 'value' => 'backup'));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'filepath', 'type' => 'hidden', 'value' => '/'));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'filename', 'type' => 'hidden', 'value' => ''));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'filecontextid', 'type' => 'hidden', 'value' => $usercontextid));
            $form .= \html_writer::end_tag('input');
            $form .= \html_writer::start_tag('input', array('name' => 'itemid', 'type' => 'hidden', 'value' => '0'));
            $form .= \html_writer::end_tag('input');

            $form .= \html_writer::start_tag('label', array('for' => 'contextid'));
            $form .= get_string('restoretocourse', 'backup') . '&nbsp;';
            $form .= \html_writer::end_tag('label');
            $form .= \html_writer::start_tag('select', array('name' => 'contextid', 'id' => 'contextid', 'class' => 'custom-select'));
            $form .= \html_writer::start_tag('option', array('value' => ''));
            $form .= get_string('selectacourse');
            $form .= \html_writer::end_tag('option');

            foreach($courserecs as $courserec) {
                $form .= \html_writer::start_tag('option', array('value' => $courserec->ctxid));
                $form .= $courserec->shortname;
                $form .= \html_writer::end_tag('option');
            }

            $form .= \html_writer::end_tag('select');
            $form .= \html_writer::end_tag('form');
            $form .= \html_writer::end_div();

            return $form;
        }


        /**
         * Renders the footer content
         *
         * @param block_mysites_renderable $widget Data container
         * @return string
         */
        private function render_content_footer(renderable $widget)
        {
            return html_writer::span(html_writer::link($this->page->url, get_string('refresh')));
        }

    } // class
