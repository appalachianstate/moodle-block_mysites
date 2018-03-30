
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



define(
    ['jquery', 'core/ajax', 'core/custom_interaction_events', 'core/notification',
     'core/modal_events', 'core/modal_factory'],
    function($, ajax, customEvents, notification, modalEvents, modalFactory) {

    return {

        init: function(blockinstanceid) {

            var module = this;

            // Fix up refresh link click handler
            $('#inst' + blockinstanceid + ' .footer a').on('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                var link = $(this); link.removeAttr('href');
                module.refresh(blockinstanceid);
                link.attr('href', '#');
            });

            // Fix up the button toggle for site tab navs
            // module.initsites(blockinstanceid);

            // Fix up the button toggle for courses or backup files
            module.initcorb(blockinstanceid);

            // Fix up the action link click handlers
            module.initajax(blockinstanceid);

            // Fix up the paging link click handlers
            module.initpaging(blockinstanceid);

        }, // init

        initcorb: function(blockinstanceid) {

            // Get all the [courses | backups] button groups in this block instance
            $('#inst' + blockinstanceid + '-content div[data-toggle="btns"]').each(function() {

                var buttongroup = this;

                // Fix up button group toggle events
                customEvents.define(buttongroup, [ customEvents.events.activate ]);

                // Now attach a delegate handler for that custom event to same
                $(this).on(customEvents.events.activate, 'a.btn', function() {
                    $(buttongroup).find('a.btn.active').removeClass('active');
                }); // $(this).on

            }); // each div[data-toggle="btns"]

        },

        initajax: function(blockinstanceid) {

            var module = this;

            var selector = '#inst' + blockinstanceid
                         + '-content .status-container button[data-action!="restore"]';
            $(selector).off('click').on('click', function(e) {
                e.preventDefault(); e.stopPropagation();

                var button = $(this);
                var wrapperdiv = button.closest('.status-wrapper');

                button.attr('disabled', '1');
                wrapperdiv.fadeTo('fast', .05, function() {
                    ajax.call([
                      {
                        methodname: 'block_mysites_sendrequestaction',
                        args: {
                            blockinstanceid: blockinstanceid,
                            action: button.data('action'),
                            siteid: button.data('siteid'),
                            courseid: button.data('courseid')
                        }
                      }
                    ]).shift().done(function(response) {
                        wrapperdiv.html(response);
                        module.initajax(blockinstanceid);
                        wrapperdiv.fadeTo('slow', 1);
                    }).fail(notification.exception);
                }); // .fadeTo
            }); // on

            selector = '#inst' + blockinstanceid
                     + '-content .status-container button[data-action="restore"]';
            $(selector).off('click').on('click', function(e) {
                e.preventDefault(); e.stopPropagation();

                var button = $(this);

                ajax.call([ { methodname: 'block_mysites_getcourseselect', args: { blockinstanceid: blockinstanceid } } ]).shift()
                .done(function(response) {

                    modalFactory.create({ type: modalFactory.types.SAVE_CANCEL, title: 'Restore archive', body: response }, button)
                    .done(function(modal) {
                        var modalRoot = $(modal.getRoot());
                        var savebtn = modalRoot.find('button[data-action="save"]');
                        savebtn.text('OK'); savebtn.attr('disabled', '1');
                        var filename = modalRoot.find('input[name="filename"]');
                        filename.val(button.data('filename'));
                        // Attach save handler
                        modalRoot.on(modalEvents.save, function(evt) {
                            evt.preventDefault();
                            $(modalRoot.find('form[name="form"]')).submit();
                        });
                        modalRoot.find('#contextid').change(function() {
                            if (this.value == '') { savebtn.attr('disabled', '1'); } else { savebtn.removeAttr('disabled'); }
                        });
                        // Show it
                        modal.show();
                    }); // modalFactory.create.done

                }) // ajax.call.done
                .fail(notification.exception);

            }); // on

        }, // initajax

        initpaging: function(blockinstanceid) {

            // Get all the pagination <nav> objects in this block instance
            $('#inst' + blockinstanceid + '-content nav[data-region="page-bar"]').each(function() {

                // Fix up paging bars to generate custom 'activate' event,
                // there may be multiple paging bars, one in each tab pane.
                customEvents.define(this, [ customEvents.events.activate ]);

                // Now attach a delegate handler for that custom event to same
                // DOM elements, determine if page selected and trigger that
                // event to be picked up by another handler
                $(this).on(customEvents.events.activate, 'a.page-link', function(evt, data) {
                    data.originalEvent.preventDefault();

                    // Determine if page change occured, if not get out
                    var pagebar = $(evt.delegateTarget);
                    var currpage = pagebar.data('pageNumber');

                    var selectedpage = $(this.parentNode).data('pageNumber');
                    if (selectedpage == 'first') { selectedpage = '1'; }
                    else if (selectedpage == 'last') { selectedpage = pagebar.data('pageCount'); }

                    // If no page change, get out
                    if (currpage == selectedpage) { return; }

                    // There is a page change
                    pagebar.data('pageNumber', selectedpage);
                    pagebar.find('li').each(function() { $(this).removeClass('active'); });
                    pagebar.find('li[data-page-number="' + selectedpage + '"]').addClass('active');

                    $(pagebar.parent().parent().find('div[data-page="' + currpage + '"]')).addClass('hidden');
                    $(pagebar.parent().parent().find('div[data-page="' + selectedpage + '"]')).removeClass('hidden');

                }); // $(this).on

            }); // each nav[..page-bar]

        }, // initpaging

        refresh: function(blockinstanceid) {

            var module = this;

            var content = $('#inst' + blockinstanceid + '-content');
            if (!content.length) { return; }

            var siteindex = $('#inst' + blockinstanceid + '-tablist li.nav-item a.nav-link.active').data('siteIndex');
            if (typeof siteindex == 'undefined') { siteindex = 1; }
            var corbindex = $('#inst' + blockinstanceid + '-tab-' + siteindex + ' .btn-group a.btn.active').data('corbIndex');
            if (typeof corbindex == 'undefined') { corbindex = 1; }
            var selector = '#inst' + blockinstanceid
                         + '-tab-' + siteindex
                         + ((corbindex == 2) ? '-backups' : '-courses')
                         + ' nav';
            var pageindex = $(selector).data('pageNumber');
            if (typeof pageindex == 'undefined') { pageindex = 1; }

            var parent = content.parent();
            $.when(parent.fadeTo('fast', .05)).done(function() {
                ajax.call([ {
                    methodname: 'block_mysites_getcontenttext',
                    args: { blockinstanceid: blockinstanceid, siteindex: siteindex, corbindex: corbindex, pageindex: pageindex }
                }
                ]).shift().done(function(response) {
                    content.replaceWith(response);
                    // Reset the handlers
                    module.initcorb(blockinstanceid);
                    module.initajax(blockinstanceid);
                    module.initpaging(blockinstanceid);
                    parent.fadeTo('slow', 1);
                }).fail(notification.exception);
            }); // $.when

        } // refresh

    }; // return

}); // define
