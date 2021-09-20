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
 * Push student comments to checklist plugin via ajax.
 *
 * @module     mod_checklist/student_comments
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, String) {
    return {
        init: function(cmid) {
            const classPrefix = 'studentcommentid';
            let comments = $('.studentcomment');

            Y.log('cmid: ' + cmid);
            // Store the initial state of each comment. Only want to update server if comment changed on blur.
            let currentComments = [];
            for (let i = 0; i < comments.length; i += 1) {
                let comment = comments[i];
                currentComments[i] = comment.value;
                comment.addEventListener('blur', function(e) {
                    // Update it IF it changed with the external function Ajax call.
                    if (currentComments[i] === e.target.value) {
                        Y.log('not going to update server, nothing changed.');
                    } else {
                        Y.log('Sending this student comment to server');
                        let classString = e.target.classList[0]; // studentcommentid13
                        // Get the item id from the end of the first class name, eg. studentcommentid13
                        let checklistitemid = classString.substr(classString.lastIndexOf(classPrefix) + classPrefix.length);

                        $('#checklistspinner').css('display', 'block');

                        let args = {
                              'comment': {
                                  'commenttext': e.target.value,
                                  'checklistitemid': checklistitemid,
                                  'cmid': cmid,
                              }
                        };

                        let request = {
                            methodname: 'mod_checklist_update_student_comment',
                            args: args,
                        };
                        Ajax.call([request])[0].done(function(data) {
                            $('#checklistspinner').css('display', 'none');
                            if (data === true) {
                                Y.log('updated comment successfully.');
                                currentComments[i] = e.target.value;
                            } else {
                                Notification.addNotification({
                                    message: String.get_string('update_student_comment_failed', 'mod_checklist'),
                                    type: 'error',
                                });
                            }
                        }).fail(Notification.exception);
                    }
                });
            }
        }
    };
});
