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
 * Checklist output functions.
 *
 * @package   mod_checklist
 * @copyright 2016 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_checklist\local\checklist_item;
use mod_checklist\local\output_status;
use mod_checklist\local\progress_info;

/**
 * Class mod_checklist_renderer
 */
class mod_checklist_renderer extends plugin_renderer_base {
    /**
     * Outpt required / total proress bars.
     * @param int $totalitems
     * @param int $requireditems
     * @param int $allcompleteitems
     * @param int $reqcompleteitems
     * @return string
     */
    public function progress_bars($totalitems, $requireditems, $allcompleteitems, $reqcompleteitems) {
        $out = '';

        if ($requireditems > 0 && $totalitems > $requireditems) {
            $out .= $this->progress_bar($requireditems, $reqcompleteitems, true);
        }
        $out .= $this->progress_bar($totalitems, $allcompleteitems, false);

        return $out;
    }

    /**
     * Output a single progress bar
     * @param int $totalitems
     * @param int $completeitems
     * @param bool $isrequired
     * @return string
     * @throws coding_exception
     */
    public function progress_bar($totalitems, $completeitems, $isrequired) {
        $out = '';

        $percentcomplete = $totalitems ? (($completeitems * 100.0) / $totalitems) : 0.0;
        if ($isrequired) {
            $heading = get_string('percentcomplete', 'checklist');
            $spanid = 'checklistprogressrequired';
        } else {
            $heading = get_string('percentcompleteall', 'checklist');
            $spanid = 'checklistprogressall';
        }

        // Heading.
        $heading .= ':&nbsp;';
        $heading = html_writer::div($heading, 'checklist_progress_heading');

        // Progress bar.
        $progress = '';
        $progress .= html_writer::div('&nbsp;', 'checklist_progress_inner', ['style' => "width: {$percentcomplete}%;"]);
        $progress .= html_writer::div('&nbsp;', 'checklist_progress_anim', ['style' => "width: {$percentcomplete}%;"]);
        $progress = html_writer::div($progress, 'checklist_progress_outer');
        $progress .= html_writer::span('&nbsp;'.sprintf('%0d%%', $percentcomplete), 'checklist_progress_percent');

        $out .= html_writer::span(
            $heading . $progress,
            'checklist_progress_bar',
            ['id' => $spanid]
        );

        return $out;
    }

    /**
     * Output a progress bar for use outside of the checklist plugin
     * @param int $totalitems
     * @param int $completeitems
     * @param int $width
     * @param bool $showpercent
     * @return string
     */
    public function progress_bar_external($totalitems, $completeitems, $width, $showpercent) {
        $out = '';

        $percentcomplete = $totalitems ? ($completeitems * 100.0 / $totalitems) : 0.0;

        $out .= html_writer::div('&nbsp;', 'checklist_progress_inner', ['style' => "width: {$percentcomplete}%;"]);
        $out = html_writer::div($out, 'checklist_progress_outer', ['style' => "width: $width;"]);
        if ($showpercent) {
            $out .= html_writer::span('&nbsp;'.sprintf('%0d%%', $percentcomplete), 'checklist_progress_percent');
        }
        $out .= html_writer::empty_tag('br', ['class' => 'clearer']);
        return $out;
    }

    /**
     * Get the class to use for the inline form layout.
     *
     * @return string
     */
    private static function form_inline_class(): string {
        global $CFG;
        if ($CFG->branch >= 500) {
            return 'd-flex flex-wrap align-items-center';
        }
        return 'form-inline';
    }

    /**
     * Get the class to use for form elements.
     *
     * @return string
     */
    private static function form_control_class(): string {
        global $CFG;
        if ($CFG->branch >= 500) {
            return 'mb-3';
        }
        return 'form-control';
    }

    /**
     * Output the checklist items
     * @param checklist_item[] $items
     * @param checklist_item[] $useritems
     * @param bool|int[] $groupings
     * @param string $intro
     * @param output_status $status
     * @param progress_info|null $progress
     * @param object $student (optional) the student whose checklist is being viewed (if not viewing own checklist)
     * @param object $currentuser (optional) the user whose checklist is being viewed.
     * @param int|null $cmid Course module ID
     */
    public function checklist_items($items, $useritems, $groupings, $intro, output_status $status, $progress, $student = null,
                                    $currentuser = null, $cmid = null): string {
        global $CFG;

        $out = $this->output->box_start('generalbox boxwidthwide boxaligncenter checklistbox', null,
                                        ['data-cmid' => $cmid]);

        $out .= html_writer::tag('div', '&nbsp;', ['id' => 'checklistspinner', 'data-cmid' => $cmid]);

        $thispageurl = new moodle_url($this->page->url);
        if ($student) {
            $thispageurl->param('studentid', $student->id);
        }

        $strteachername = '';
        $struserdate = '';
        $strteacherdate = '';
        if ($status->is_viewother()) {
            $out .= '<h2>'.get_string('checklistfor', 'checklist').' '.fullname($student, true).'</h2>';
            $out .= '&nbsp;';
            $out .= '<form style="display: inline;" action="'.$thispageurl->out_omit_querystring().'" method="get">';
            $out .= html_writer::input_hidden_params($thispageurl, ['studentid']);
            $out .= '<input type="submit" class="btn btn-secondary" name="viewall" value="'
                .get_string('viewall', 'checklist').'" />';
            $out .= '</form>';

            if (!$status->is_editcomments()) {
                $out .= '<form style="display: inline;" action="'.$thispageurl->out_omit_querystring().'" method="get">';
                $out .= html_writer::input_hidden_params($thispageurl);
                $out .= '<input type="hidden" name="editcomments" value="on" />';
                $out .= ' <input type="submit" class="btn btn-secondary" name="viewall" value="'.
                    get_string('addcomments', 'checklist').'" />';
                $out .= '</form>';
            }
            $out .= '<form style="display: inline;" action="'.$thispageurl->out_omit_querystring().'" method="get">';
            $out .= html_writer::input_hidden_params($thispageurl);
            $out .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            $out .= '<input type="hidden" name="action" value="toggledates" />';
            $out .= ' <input type="submit" class="btn btn-secondary" name="toggledates" value="'.
                get_string('toggledates', 'checklist').'" />';
            $out .= '</form>';

            $strteacherdate = get_string('teacherdate', 'mod_checklist');
            $struserdate = get_string('userdate', 'mod_checklist');
            $strteachername = get_string('teacherid', 'mod_checklist');
        }

        $out .= $intro;
        $out .= '<br/>';

        if ($status->is_showprogressbar() && $progress) {
            $out .= $this->progress_bars($progress->totalitems, $progress->requireditems,
                                         $progress->allcompleteitems, $progress->requiredcompleteitems);
        }

        if (!$items) {
            $out .= get_string('noitems', 'checklist');
        } else {
            $focusitem = false;
            if ($status->is_updateform()) {
                if ($status->is_canaddown() && !$status->is_viewother()) {
                    $out .= '<form style="display:inline;" action="'.$thispageurl->out_omit_querystring().'" method="get">';
                    $out .= html_writer::input_hidden_params($thispageurl);
                    if ($status->is_addown()) {
                        // Switch on for any other forms on this page (but off if this form submitted).
                        $thispageurl->param('useredit', 'on');
                        $out .= '<input type="submit" class="btn btn-secondary" name="submit" value="'.
                            get_string('addownitems-stop', 'checklist').'" />';
                    } else {
                        $out .= '<input type="hidden" name="useredit" value="on" />';
                        $out .= '<input type="submit" class="btn btn-secondary" name="submit" value="'.
                            get_string('addownitems', 'checklist').'" />';
                    }
                    $out .= '</form>';
                }

                $out .= '<form action="'.$thispageurl->out_omit_querystring()
                    .'" class="" method="post" autocomplete="off">';
                $out .= html_writer::input_hidden_params($thispageurl);
                $out .= '<input type="hidden" name="action" value="updatechecks" />';
                $out .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            }

            if ($useritems) {
                reset($useritems);
            }

            if ($status->is_teachermarklocked()) {
                $out .= '<p class="checklistwarning">'.get_string('lockteachermarkswarning', 'checklist').'</p>';
                $out .= '<div style="flex-basis:100%; height:0"></div>';
            }

            $out .= '<ol class="checklist" id="checklistouter">';
            $currindent = 0;
            foreach ($items as $item) {

                if ($item->hidden) {
                    continue;
                }

                if ($status->is_checkgroupings() && $item->groupingid) {
                    if (!in_array($item->groupingid, $groupings)) {
                        continue; // Current user is not a member of this item's grouping, so skip.
                    }
                }

                while ($item->indent > $currindent) {
                    $currindent++;
                    $out .= '<ol class="checklist">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    $out .= '</ol>';
                }
                $itemname = '"item'.$item->id.'"';
                $checked = '';
                if ($status->is_updateform() || $status->is_viewother() || $status->is_userreport()) {
                    if ($item->is_checked_student()) {
                        $checked = ' checked="checked" ';
                    }
                }
                if ($status->is_viewother() || $status->is_userreport()) {
                    $checked .= ' disabled="disabled" ';
                } else if (!$status->is_overrideauto()) {
                    if ($item->is_auto_item()) {
                        $checked .= ' disabled="disabled" ';
                    }
                }
                switch ($item->colour) {
                    case 'red':
                        $itemcolour = 'itemred';
                        break;
                    case 'orange':
                        $itemcolour = 'itemorange';
                        break;
                    case 'green':
                        $itemcolour = 'itemgreen';
                        break;
                    case 'purple':
                        $itemcolour = 'itempurple';
                        break;
                    default:
                        $itemcolour = 'itemblack';
                }

                $margin = 'ms-1';
                if ($CFG->branch < 500) {
                    $margin = 'ml-1';
                }

                $checkclass = '';
                if ($item->is_heading()) {
                    $optional = ' class="itemheading '.$itemcolour.' ' . $margin . '" ';
                } else if ($item->is_required()) {
                    $optional = ' class="'.$itemcolour.' ' . $margin . '" ';
                } else {
                    $optional = ' class="itemoptional '.$itemcolour.' ' . $margin . '" ';
                    $checkclass = ' itemoptional';
                }

                $out .= '<li>';
                if ($status->is_showteachermark()) {
                    if (!$item->is_heading()) {
                        if ($status->is_viewother()) {
                            $opts = [
                                CHECKLIST_TEACHERMARK_UNDECIDED => '',
                                CHECKLIST_TEACHERMARK_YES => get_string('yes'),
                                CHECKLIST_TEACHERMARK_NO => get_string('no'),
                            ];
                            $attr = ['id' => 'item'.$item->id]; // TODO davo - fix itemname handling.
                            if ($status->is_teachermarklocked() && $item->is_checked_teacher()) {
                                $attr['disabled'] = 'disabled';
                            } else if (!$status->is_showcheckbox() && !$status->is_overrideauto() && $item->is_auto_item()) {
                                // For teacher-only checklists with autoupdate not allowed to override, disable changing of
                                // automatic update items.
                                $attr['disabled'] = 'disabled';
                            }

                            $out .= html_writer::select($opts, "items[{$item->id}]", $item->teachermark, false, $attr);

                        } else {
                            $out .= html_writer::empty_tag('img', [
                                'src' => $item->get_teachermark_image_url(),
                                'alt' => $item->get_teachermark_text(),
                                'title' => $item->get_teachermark_text(),
                                'class' => $item->get_teachermark_class(),
                            ]);
                        }
                    }
                }
                if ($status->is_showcheckbox()) {
                    if (!$item->is_heading()) {
                        $id = ' id='.$itemname.' ';
                        if ($status->is_viewother() && $status->is_showteachermark()) {
                            $id = '';
                        }
                        $out .= '<input class="checklistitem'.$checkclass.'" type="checkbox" data-cmid="'.$cmid.'"'.
                            ' class="checkbox-inline" name="items[]" '.$id.$checked.
                            ' value="'.$item->id.'" />';
                    }
                }
                $out .= '<label for='.$itemname.$optional.'>'.format_string($item->displaytext).'</label>';
                $out .= $this->item_grouping($item);

                $out .= $this->checklist_item_link($item);

                if ($status->is_addown()) {
                    $out .= '&nbsp;<a href="'.$thispageurl->out(true, [
                            'itemid' => $item->id, 'sesskey' => sesskey(), 'action' => 'startadditem',
                        ]).'">';
                    $title = get_string('additemalt', 'checklist');
                    $out .= $this->output->pix_icon('add', $title, 'mod_checklist', ['title' => $title]).'</a>';
                }

                if ($item->duetime) {
                    if ($item->duetime > time()) {
                        $out .= '<span class="checklist-itemdue"> '
                            .userdate($item->duetime, get_string('strftimedate')).'</span>';
                    } else {
                        $out .= '<span class="checklist-itemoverdue"> '
                            .userdate($item->duetime, get_string('strftimedate')).'</span>';
                    }
                }

                if ($status->is_showcompletiondates()) {
                    if (!$item->is_heading()) {
                        if ($status->is_showteachermark() && $item->teachertimestamp) {
                            if ($item->get_teachername()) {
                                $out .= '<span class="itemteachername" title="'.$strteachername.'">'.
                                    $item->get_teachername().'</span>';
                            }
                            $out .= '<span class="itemteacherdate" title="'.$strteacherdate.'">'.
                                userdate($item->teachertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                        if ($status->is_showcheckbox() && $item->usertimestamp) {
                            $out .= '<span class="itemuserdate" title="'.$struserdate.'">'.
                                userdate($item->usertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                    }
                }

                if ($status->is_teachercomments()) {
                    if ($comment = $item->get_comment()) {
                        $out .= ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            $out .= '<a href="'.$comment->get_commentby_url().'">'.$comment->get_commentby_name().'</a>: ';
                        }
                        if ($status->is_editcomments()) {
                            $outid = '';
                            if (!$focusitem) {
                                $focusitem = 'firstcomment';
                                $outid = ' id="firstcomment" ';
                            }
                            $out .= '<input type="text" class="' . self::form_control_class() . ' form-text-inline"'.
                                ' name="teachercomment['.$item->id.']" value="'.s($comment->text).
                                '" '.$outid.'/>';
                        } else {
                            $out .= s($comment->text);
                        }
                        $out .= '&nbsp;</span>';
                    } else if ($status->is_editcomments()) {
                        $out .= '&nbsp;<input type="text" class="' . self::form_control_class() . ' form-text-inline"'.
                            ' name="teachercomment['.$item->id.']" />';
                    }
                }

                $student = $currentuser ?? $student;
                if ($student && $status->is_studentcomments()) {
                    $comment = $item->get_student_comment();
                    $isstudent = !$status->is_viewother();
                    if ($isstudent || ($comment && $comment->get('text'))) {
                        $context = (object)[
                            'itemid' => $item->id,
                            'commenttext' => $comment ? $comment->get('text') : null,
                            'itemdisplaytext' => $item->displaytext,
                            'isstudent' => $isstudent,
                            'studenturl' => $this->get_user_url($student->id, $status->get_courseid()),
                            'studentname' => fullname($student),
                        ];
                        $out .= $this->render_from_template('mod_checklist/student_comment', $context);
                    }
                }

                $out .= '</li>';

                $inline = self::form_inline_class();

                // Output any user-added items.
                if ($useritems) {
                    /** @var checklist_item $useritem */
                    $useritem = current($useritems);

                    if ($useritem && ($useritem->position == $item->position)) {
                        $thisitemurl = new moodle_url($thispageurl, ['action' => 'updateitem', 'sesskey' => sesskey()]);

                        $out .= '<ol class="checklist">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($status->is_updateform() && $useritem->is_checked_student()) ? ' checked="checked" ' : '';
                            if ($useritem->is_editme()) {
                                $itemtext = explode("\n", $useritem->displaytext, 2);
                                $itemtext[] = '';
                                $text = $itemtext[0];
                                $note = $itemtext[1];
                                $thisitemurl->param('itemid', $useritem->id);

                                $out .= '<li>';
                                $out .= '<div style="float: left;">';
                                if ($status->is_showcheckbox()) {
                                    $out .= '<input class="checklistitem itemoptional checkbox-inline" type="checkbox"'.
                                        ' name="items[]" id='.
                                        $itemname.$checked.' disabled="disabled" value="'.$useritem->id.'" />';
                                }
                                $out .= '<form style="display:inline" class="' . $inline . '" action="'.
                                    $thisitemurl->out_omit_querystring().
                                    '" method="post">';
                                $out .= html_writer::input_hidden_params($thisitemurl);
                                $out .= '<input type="text" class="' . self::form_control_class() .
                                    ' form-text-inline" size="'.
                                    CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($text).
                                    '" id="updateitembox" />';
                                $out .= '<input type="submit" class="btn btn-secondary" name="updateitem" value="'.
                                    get_string('updateitem', 'checklist').'" />';
                                $out .= '<br />';
                                $out .= '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                $out .= '</form>';
                                $out .= '</div>';

                                $out .= '<form style="display:inline;" class="' . $inline . '" action="'.
                                    $thispageurl->out_omit_querystring().
                                    '" method="get">';
                                $out .= html_writer::input_hidden_params($thispageurl);
                                $out .= '<input type="submit" class="btn btn-secondary" name="canceledititem" value="'.
                                    get_string('canceledititem', 'checklist').'" />';
                                $out .= '</form>';
                                $out .= '<br style="clear: both;" />';
                                $out .= '</li>';

                                $focusitem = 'updateitembox';
                            } else {
                                $out .= '<li>';
                                if ($status->is_showcheckbox()) {
                                    $out .= '<input class="checklistitem itemoptional checkbox-inline" type="checkbox"'.
                                        ' name="items[]" id='.
                                        $itemname.$checked.' value="'.$useritem->id.'" />';
                                }
                                $splittext = explode("\n", s($useritem->displaytext), 2);
                                $splittext[] = '';
                                $text = $splittext[0];
                                $note = str_replace("\n", '<br />', $splittext[1]);
                                $out .= '<label class="useritem" for='.$itemname.'>'.$text.'</label>';

                                if ($status->is_addown()) {
                                    $baseurl = $thispageurl.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    $out .= '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = get_string('edititem', 'checklist');
                                    $out .= $this->output->pix_icon('t/edit', $title, 'moodle',
                                                                    ['title' => $title]).'</a>';

                                    $out .= '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = get_string('deleteitem', 'checklist');
                                    $out .= $this->output->pix_icon('remove', $title, 'mod_checklist',
                                                                    ['title' => $title]).'</a>';
                                }
                                if ($note != '') {
                                    $out .= '<div class="note">'.$note.'</div>';
                                }

                                $out .= '</li>';
                            }
                            $useritem = next($useritems);
                        }
                        $out .= '</ol>';
                    }
                }

                if ($status->is_addown() && ($item->id == $status->get_additemafter())) {
                    $thisitemurl = clone $thispageurl;
                    $thisitemurl->param('action', 'additem');
                    $thisitemurl->param('position', $item->position);
                    $thisitemurl->param('sesskey', sesskey());

                    $out .= '<ol class="checklist"><li>';
                    $out .= '<div style="float: left;">';
                    $out .= '<form action="' . $thispageurl->out_omit_querystring() . '" class="' . $inline .
                        '" method="post">';
                    $out .= html_writer::input_hidden_params($thisitemurl);
                    if ($status->is_showcheckbox()) {
                        $out .= '<input type="checkbox" class="checkbox-inline" disabled="disabled" />';
                    }
                    $out .= '<input type="text" class="' . self::form_control_class() . ' form-text-inline" size="'.
                        CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    $out .= '<input type="submit" class="btn btn-secondary" name="additem" value="'.
                        get_string('additem', 'checklist').'" />';
                    $out .= '<br />';
                    $out .= '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
                    $out .= '</form>';
                    $out .= '</div>';

                    $out .= '<form style="display:inline" action="'.$thispageurl->out_omit_querystring().'" method="get">';
                    $out .= html_writer::input_hidden_params($thispageurl);
                    $out .= '<input type="submit" class="btn btn-secondary" name="canceledititem" value="'.
                        get_string('canceledititem', 'checklist').'" />';
                    $out .= '</form>';
                    $out .= '<br style="clear: both;" />';
                    $out .= '</li></ol>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                }
            }
            $out .= '</ol>';

            if ($status->is_updateform()) {
                $out .= '<div style="flex-basis:100%; height:0"></div>';
                $out .= '<input id="checklistsavechecks" type="submit" name="submit" value="'.
                    get_string('savechecks', 'checklist').'" />';
                if ($status->is_viewother()) {
                    $out .= '&nbsp;<input type="submit" class="btn btn-secondary" name="save" value="'.
                        get_string('savechecks', 'mod_checklist').'" />';
                    $out .= '&nbsp;<input type="submit" class="btn btn-secondary" name="savenext" value="'.
                        get_string('saveandnext').'" />';
                    $out .= '&nbsp;<input type="submit" class="btn btn-secondary" name="viewnext" value="'.
                        get_string('next').'" />';
                }
                $out .= '</form>';
            }

            if ($focusitem) {
                $out .= '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
            }

            if ($status->is_addown()) {
                $out .= '<script type="text/javascript">';
                $out .= 'function confirmdelete(url) {';
                $out .= 'if (confirm("'.get_string('confirmdeleteitem', 'checklist').'")) { window.location = url; } ';
                $out .= '} ';
                $out .= 'var links = document.getElementById("checklistouter").getElementsByTagName("a"); ';
                $out .= 'for (var i in links) { ';
                $out .= 'if (links[i].className == "deleteicon") { ';
                $out .= 'var url = links[i].href;';
                $out .= 'links[i].href = "#";';
                $out .= 'links[i].onclick = new Function( "confirmdelete(\'"+url+"\')" ) ';
                $out .= '}} ';
                $out .= '</script>';
            }
        }

        $out .= $this->output->box_end();

        return $out;
    }

    /**
     * Output the item link
     * @param checklist_item $item
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function checklist_item_link(checklist_item $item) {
        $out = '';
        if ($url = $item->get_link_url()) {
            $attrs = [];
            $out .= '&nbsp;';
            switch ($item->get_link_type()) {
                case checklist_item::LINK_MODULE:
                    $icon = $this->output->pix_icon('follow_link', get_string('linktomodule', 'checklist'),
                                                    'mod_checklist');
                    break;
                case checklist_item::LINK_COURSE:
                    $icon = $this->output->pix_icon('i/course', get_string('linktocourse', 'checklist'));
                    break;
                case checklist_item::LINK_URL:
                    $icon = $this->output->pix_icon('follow_link', get_string('linktourl', 'checklist'),
                                                    'mod_checklist');
                    if ($item->openlinkinnewwindow) {
                        $attrs['target'] = '_blank';
                    }
                    break;
            }
            $out .= html_writer::link($url, $icon, $attrs);
        }
        return $out;
    }

    /**
     * Output edit items list
     * @param checklist_item[] $items
     * @param output_status $status
     */
    public function checklist_edit_items($items, $status): string {
        $out = $this->output->box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        $thispageurl = new moodle_url($this->page->url, ['sesskey' => sesskey()]);
        if ($status->get_additemafter()) {
            $thispageurl->param('additemafter', $status->get_additemafter());
        }
        if ($status->is_editdates()) {
            $thispageurl->param('editdates', 'on');
        }
        if ($status->get_itemid()) {
            $thispageurl->param('itemid', $status->get_itemid());
        }

        if ($status->is_autoupdatewarning()) {
            switch ($status->get_autoupdatewarning()) {
                case CHECKLIST_MARKING_STUDENT:
                    $out .= '<p>'.get_string('autoupdatewarning_student', 'checklist').'</p>';
                    break;
                case CHECKLIST_MARKING_TEACHER:
                    $out .= '<p>'.get_string('autoupdatewarning_teacher', 'checklist').'</p>';
                    break;
                default:
                    $out .= '<p class="checklistwarning">'.get_string('autoupdatewarning_both', 'checklist').'</p>';
                    break;
            }
        }

        // Start the ordered list of checklist items.
        $attr = ['class' => 'checklist'];
        if ($status->is_editdates() || $status->is_editlinks()) {
            $attr['class'] .= ' checklist-extendedit';
        }
        $out .= html_writer::start_tag('ol', $attr);

        $inline = self::form_inline_class();

        // Output each item.
        if ($items) {
            $lastitem = count($items);
            $lastindent = 0;

            $out .= html_writer::start_tag('form',
                                           ['action' => $thispageurl->out_omit_querystring(), 'method' => 'post']);
            $out .= html_writer::input_hidden_params($thispageurl);

            if ($status->is_autopopulate()) {
                $out .= html_writer::empty_tag('input', [
                    'type' => 'submit', 'name' => 'showhideitems',
                    'value' => get_string('showhidechecked', 'checklist'),
                    'class' => 'btn btn-secondary',
                ]);
            }

            foreach ($items as $item) {

                while ($item->indent > $currindent) {
                    $currindent++;
                    $out .= '<ol class="checklist">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    $out .= '</ol>';
                }

                $itemname = '"item'.$item->id.'"';
                $itemurl = new moodle_url($thispageurl, ['itemid' => $item->id]);

                switch ($item->colour) {
                    case 'red':
                        $itemcolour = 'itemred';
                        $nexticon = 'colour_orange';
                        break;
                    case 'orange':
                        $itemcolour = 'itemorange';
                        $nexticon = 'colour_green';
                        break;
                    case 'green':
                        $itemcolour = 'itemgreen';
                        $nexticon = 'colour_purple';
                        break;
                    case 'purple':
                        $itemcolour = 'itempurple';
                        $nexticon = 'colour_black';
                        break;
                    default:
                        $itemcolour = 'itemblack';
                        $nexticon = 'colour_red';
                }

                $autoitem = ($status->is_autopopulate()) && ($item->moduleid != 0);
                if ($autoitem) {
                    $autoclass = ' itemauto';
                } else {
                    $autoclass = '';
                }
                $hasauto = $hasauto || ($item->moduleid != 0);

                if ($item->is_editme()) {
                    $out .= '<li class="checklist-edititem ' . $inline . '">';
                } else {
                    $out .= '<li>';
                }

                $out .= html_writer::start_span('', ['style' => 'display: inline-block; width: 16px;']);
                if ($autoitem && $item->hidden != CHECKLIST_HIDDEN_BYMODULE) {
                    $out .= html_writer::checkbox('items['.$item->id.']', $item->id, false, '',
                                                  ['title' => $item->displaytext, 'class' => 'checkbox-inline']);
                }
                $out .= html_writer::end_span();

                // Item optional toggle.
                if ($item->is_optional()) {
                    $title = get_string('optionalitem', 'checklist');
                    $out .= '<a href="'.$itemurl->out(true, ['action' => 'makeheading']).'">';
                    $out .= $this->output->pix_icon('empty_box', $title, 'mod_checklist',
                                                    ['title' => $title]).'</a>&nbsp;';
                    $optional = ' class="itemoptional '.$itemcolour.$autoclass.'" ';
                } else if ($item->is_heading()) {
                    if ($item->hidden) {
                        $title = get_string('headingitem', 'checklist');
                        $out .= $this->output->pix_icon('no_box', $title, 'mod_checklist',
                                                        ['title' => $title]).'&nbsp;';
                        $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                    } else {
                        $title = get_string('headingitem', 'checklist');
                        if (!$autoitem) {
                            $out .= '<a href="'.$itemurl->out(true, ['action' => 'makerequired']).'">';
                        }
                        $out .= $this->output->pix_icon('no_box', $title, 'mod_checklist', ['title' => $title]);
                        if (!$autoitem) {
                            $out .= '</a>';
                        }
                        $out .= '&nbsp;';
                        $optional = ' class="itemheading '.$itemcolour.$autoclass.'" ';
                    }
                } else if ($item->hidden) {
                    $title = get_string('requireditem', 'checklist');
                    $out .= $this->output->pix_icon('tick_box', $title, 'mod_checklist', ['title' => $title]).'&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } else {
                    $title = get_string('requireditem', 'checklist');
                    $out .= '<a href="'.$itemurl->out(true, ['action' => 'makeoptional']).'">';
                    $out .= $this->output->pix_icon('tick_box', $title, 'mod_checklist',
                                                    ['title' => $title]).'</a>&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.'"';
                }

                if ($item->is_editme()) {
                    // Edit item form.
                    $focusitem = 'updateitembox';
                    $addatend = false;
                    $out .= $this->edit_item_form($status, $item);

                } else {
                    // Item text.
                    $out .= '<label for='.$itemname.$optional.'>'.format_string($item->displaytext).'</label> ';

                    // Grouping.
                    $out .= $this->item_grouping($item);

                    // Item colour.
                    if (!empty(get_config('mod_checklist', 'showcolorchooser'))) {
                        $out .= '<a href="'.$itemurl->out(true, ['action' => 'nextcolour']).'">';
                        $title = get_string('changetextcolour', 'checklist');
                        $out .= $this->output->pix_icon($nexticon, $title, 'mod_checklist', ['title' => $title]).'</a>';
                    }

                    // Edit item.
                    if (!$autoitem) {
                        $edititemurl = new moodle_url($itemurl, ['action' => 'edititem']);
                        $edititemurl->remove_params('additemafter');
                        $out .= '<a href="'.$edititemurl->out().'">';
                        $title = get_string('edititem', 'checklist');
                        $out .= $this->output->pix_icon('t/edit', $title, 'moodle', ['title' => $title]).'</a>&nbsp;';
                    }

                    // Change item indent.
                    if (!$autoitem && $item->indent > 0) {
                        $out .= '<a href="'.$itemurl->out(true, ['action' => 'unindentitem']).'">';
                        $title = get_string('unindentitem', 'checklist');
                        $out .= $this->output->pix_icon('t/left', $title, 'moodle', ['title' => $title]).'</a>';
                    }
                    if (!$autoitem && ($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent + 1) > $currindent)) {
                        $out .= '<a href="'.$itemurl->out(true, ['action' => 'indentitem']).'">';
                        $title = get_string('indentitem', 'checklist');
                        $out .= $this->output->pix_icon('t/right', $title, 'moodle', ['title' => $title]).'</a>';
                    }

                    $out .= '&nbsp;';

                    // Move item up/down.
                    if (!$autoitem && $item->position > 1) {
                        $out .= '<a href="'.$itemurl->out(true, ['action' => 'moveitemup']).'">';
                        $title = get_string('moveitemup', 'checklist');
                        $out .= $this->output->pix_icon('t/up', $title, 'moodle', ['title' => $title]).'</a>';
                    }
                    if (!$autoitem && $item->position < $lastitem) {
                        $out .= '<a href="'.$itemurl->out(true, ['action' => 'moveitemdown']).'">';
                        $title = get_string('moveitemdown', 'checklist');
                        $out .= $this->output->pix_icon('t/down', $title, 'moodle', ['title' => $title]).'</a>';
                    }

                    // Hide/delete item.
                    if ($autoitem) {
                        if ($item->hidden != CHECKLIST_HIDDEN_BYMODULE) {
                            $out .= '&nbsp;<a href="'.$itemurl->out(true, ['action' => 'deleteitem']).'">';
                            if ($item->hidden == CHECKLIST_HIDDEN_MANUAL) {
                                $title = get_string('show');
                                $out .= $this->output->pix_icon('t/show', $title, 'moodle', ['title' => $title]).'</a>';
                            } else {
                                $title = get_string('hide');
                                $out .= $this->output->pix_icon('t/hide', $title, 'moodle', ['title' => $title]).'</a>';
                            }
                        }
                    } else {
                        $out .= '&nbsp;<a href="'.$itemurl->out(true, ['action' => 'deleteitem']).'">';
                        $title = get_string('deleteitem', 'checklist');
                        $out .= $this->output->pix_icon('t/delete', $title, 'moodle', ['title' => $title]).'</a>';
                    }

                    // Add item icon.
                    $out .= '&nbsp;&nbsp;&nbsp;<a href="'.$itemurl->out(true, ['action' => 'startadditem']).'">';
                    $title = get_string('additemhere', 'checklist');
                    $out .= $this->output->pix_icon('add', $title, 'mod_checklist', ['title' => $title]).'</a>';

                    // Due time.
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            $out .= '<span class="checklist-itemdue"> '
                                .userdate($item->duetime, get_string('strftimedate')).'</span>';
                        } else {
                            $out .= '<span class="checklist-itemoverdue"> '.
                                userdate($item->duetime, get_string('strftimedate')).'</span>';
                        }
                    }

                    // Link (if any).
                    $out .= $this->checklist_item_link($item);
                }

                if ($status->get_additemafter() == $item->id) {
                    $addatend = false;
                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                    $out .= $this->add_item_form($status, $thispageurl, $currindent, $item->position + 1);
                }

                $lastindent = $currindent;

                $out .= '</li>';
            }

            $out .= html_writer::end_tag('form');
        }

        if ($addatend) {
            if (!$focusitem) {
                $focusitem = 'additembox';
            }
            $out .= $this->add_item_form($status, $thispageurl, $currindent);
        }
        $out .= '</ol>';
        while ($currindent) {
            $currindent--;
            $out .= '</ol>';
        }

        // Edit dates button.
        $editdatesurl = new moodle_url($thispageurl);
        $editdatesurl->remove_params('sesskey');
        if ($status->is_editdates()) {
            $editdatesurl->remove_params('editdates');
            $editdatesstr = get_string('editdatesstop', 'mod_checklist');
        } else {
            $editdatesurl->param('editdates', 'on');
            $editdatesstr = get_string('editdatesstart', 'mod_checklist');
        }
        $out .= $this->output->single_button($editdatesurl, $editdatesstr, 'get');

        // Remove autopopulate button.
        if (!$status->is_autopopulate() && $hasauto) {
            $removeautourl = new moodle_url($thispageurl, ['removeauto' => 1]);
            $out .= $this->output->single_button($removeautourl, get_string('removeauto', 'mod_checklist'));
        }

        if ($focusitem) {
            $out .= '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
        }

        $out .= $this->output->box_end();

        return $out;
    }

    /**
     * Output the edit date form
     * @param int $ts
     * @return string
     * @throws coding_exception
     */
    protected function edit_date_form($ts = 0) {
        $out = '';

        $out .= '<br>';
        $id = uniqid();
        if ($ts == 0) {
            $disabled = true;
            $date = usergetdate(time());
        } else {
            $disabled = false;
            $date = usergetdate($ts);
        }
        $day = $date['mday'];
        $month = $date['mon'];
        $year = $date['year'];

        // Day.
        $opts = range(1, 31);
        $opts = array_combine($opts, $opts);
        $out .= html_writer::select($opts, 'duetime[day]', $day, null, ['id' => "timedueday{$id}"]);

        // Month.
        $opts = [];
        for ($i = 1; $i <= 12; $i++) {
            $opts[$i] = userdate(gmmktime(12, 0, 0, $i, 15, 2000), "%B");
        }
        $out .= html_writer::select($opts, 'duetime[month]', $month, null, ['id' => "timeduemonth{$id}"]);

        // Year.
        $today = usergetdate(time());
        $thisyear = $today['year'];
        $opts = range($thisyear - 5, $thisyear + 10);
        $opts = array_combine($opts, $opts);
        $out .= html_writer::select($opts, 'duetime[year]', $year, null, ['id' => "timedueyear{$id}"]);

        // Disabled checkbox.
        $attr = [
            'type' => 'checkbox', 'class' => 'checkbox-inline', 'name' => 'duetimedisable',
            'id' => "timeduedisable{$id}", 'onclick' => "toggledate{$id}()",
        ];
        if ($disabled) {
            $attr['checked'] = 'checked';
        }
        $out .= html_writer::empty_tag('input', $attr);
        $out .= html_writer::label(get_string('disable'), "timeduedisable{$id}");

        // Script to disable items when unchecked.
        $out .= <<< ENDSCRIPT
<script type="text/javascript">
    function toggledate{$id}() {
        var disable = document.getElementById('timeduedisable{$id}').checked;
        var day = document.getElementById('timedueday{$id}');
        var month = document.getElementById('timeduemonth{$id}');
        var year = document.getElementById('timedueyear{$id}');
        if (disable) {
            day.setAttribute('disabled','disabled');
            month.setAttribute('disabled', 'disabled');
            year.setAttribute('disabled', 'disabled');
        } else {
            day.removeAttribute('disabled');
            month.removeAttribute('disabled');
            year.removeAttribute('disabled');
        }
    }
    toggledate{$id}();
</script>
ENDSCRIPT;

        return html_writer::span($out, 'checklistformitem');
    }

    /**
     * Output the edit link form
     * @param output_status $status
     * @param checklist_item $item (optional)
     * @return string
     */
    protected function edit_link_form(output_status $status, $item = null) {
        global $CFG;
        $out = '';

        $out .= '<br>';
        $out .= html_writer::tag('label', get_string('linkto', 'mod_checklist')).' ';
        if ($status->is_allowcourselinks()) {
            $selected = $item ? $item->linkcourseid : null;
            $out .= html_writer::select(checklist_class::get_linkable_courses(), 'linkcourseid', $selected,
                                        ['' => get_string('choosecourse', 'mod_checklist')]);
            $out .= ' '.get_string('or', 'mod_checklist').' ';
        }
        $out .= html_writer::label(get_string('url'), 'id_linkurl', true, ['class' => 'accesshide']);
        $attr = [
            'type' => 'text',
            'name' => 'linkurl',
            'id' => 'id_linkurl',
            'size' => 40,
            'value' => $item ? $item->linkurl : '',
            'placeholder' => get_string('enterurl', 'mod_checklist'),
            'class' => self::form_control_class(),
        ];
        $out .= html_writer::empty_tag('input', $attr);

        $attr = [
            'type' => 'checkbox',
            'class' => self::form_control_class(),
            'id' => 'id_openlinkinnewwindow',
        ];
        $out .= html_writer::checkbox(
            'openlinkinnewwindow',
            1,
            $item ? (bool)$item->openlinkinnewwindow : false,
            get_string('openlinkinnewwindow', 'mod_checklist'),
            $attr
        );

        return html_writer::span($out, 'checklistformitem');
    }

    /**
     * Form to select the grouping for the current item
     *
     * @param output_status $status
     * @param checklist_item $item (optional)
     * @return string
     */
    protected function edit_grouping_form(output_status $status, $item = null) {
        $out = '';

        $out .= '<br>';
        $out .= html_writer::label(get_string('grouping', 'mod_checklist'), 'id_grouping').' ';
        $selected = $item ? $item->groupingid : null;
        $groupings = checklist_class::get_course_groupings($status->get_courseid());
        $out .= html_writer::select($groupings, 'groupingid', $selected,
                                    [0 => get_string('anygrouping', 'mod_checklist')],
                                    ['id' => 'id_grouping']);

        return html_writer::span($out, 'checklistformitem');
    }

    /**
     * Output the add item form
     * @param output_status $status
     * @param moodle_url $thispageurl
     * @param int $currindent
     * @param int $position (optional)
     * @return string
     */
    protected function add_item_form(output_status $status, moodle_url $thispageurl, $currindent, $position = null) {
        $out = '';
        $addingatend = ($position === null);
        $inline = self::form_inline_class();

        $out .= '<li class="checklist-edititem ' . $inline . '">';
        if ($addingatend) {
            $out .= '<form action="'.$thispageurl->out_omit_querystring().'" class="' . $inline . '" method="post">';
            $out .= html_writer::input_hidden_params($thispageurl);
        }

        if ($addingatend) {
            $out .= '<input type="hidden" name="action" value="additem" />';
        } else {
            $out .= '<input type="hidden" name="position" value="'.$position.'" />';
        }
        $out .= '<input type="hidden" name="indent" value="'.$currindent.'" />';
        $out .= $this->output->pix_icon('tick_box', '', 'mod_checklist');
        $out .= '<input type="text" class="' . self::form_control_class() . ' form-text-inline" size="'.
            CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
        $out .= '<input type="submit" class="btn btn-secondary" name="additem" value="'.
            get_string('additem', 'checklist').'" />';
        if (!$addingatend) {
            $out .= '<input type="submit" class="btn btn-secondary" name="canceledititem" value="'.
                get_string('canceledititem', 'checklist').'" />';
        }
        if ($status->is_editlinks()) {
            $out .= $this->edit_link_form($status);
        }
        if ($status->is_editdates()) {
            $out .= $this->edit_date_form();
        }
        if ($status->is_editgrouping()) {
            $out .= $this->edit_grouping_form($status);
        }

        if ($addingatend) {
            $out .= '</form>';
        }
        $out .= '</li>';

        return $out;
    }

    /**
     * Output the edit item form
     * @param output_status $status
     * @param checklist_item $item
     * @return string
     */
    protected function edit_item_form(output_status $status, checklist_item $item) {
        $out = '';

        $out .= '<input type="text" class="' . self::form_control_class() . ' form-text-inline" size="'.
            CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.
            s($item->displaytext).'" id="updateitembox" />';
        $out .= '<input type="submit" class="btn btn-secondary" name="updateitem" value="'.
            get_string('updateitem', 'checklist').'" />';
        $out .= '<input type="submit" class="btn btn-secondary" name="canceledititem" value="'.
            get_string('canceledititem', 'checklist').'" />';
        if ($status->is_editlinks()) {
            $out .= $this->edit_link_form($status, $item);
        }
        if ($status->is_editdates()) {
            $out .= $this->edit_date_form($item->duetime);
        }
        if ($status->is_editgrouping()) {
            $out .= $this->edit_grouping_form($status, $item);
        }

        return $out;
    }

    /**
     * Output the item grouping details
     * @param object $item
     * @return string
     */
    public function item_grouping($item) {
        $out = '';
        if ($item->groupingname) {
            $out .= ' ';
            $out .= html_writer::span("({$item->groupingname})", 'checklist-groupingname');
            $out .= ' ';
        }
        return $out;
    }

    /**
     * Get the user profile URL for the commenting user
     * @param int $userid id of the user.
     * @param int $courseid id of the course to make the link point towards.
     * @return moodle_url the user profile url.
     */
    public function get_user_url($userid, $courseid) {
        return new moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid]);
    }

}
