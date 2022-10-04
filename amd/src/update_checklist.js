// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Update checklists
 *
 * @module      mod_checklist/update_checklist
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Notification from 'core/notification';

let checklists = [];
let updateList = [];
let updateTimeout = null;
let sesskey = null;

export const init = (cmid, givensesskey, updateprogress) => {
    sesskey = givensesskey;
    let checklist = {
        'cmid': cmid,
        'items': [],
        'optionalcount': 0,
        'requiredcount': 0,
        'requiredchecked': 0,
        'optionalchecked': 0,
        'updateprogress': updateprogress,
    };

    // Initialise the given checklist cmid
    let items = $('.checklistitem[data-cmid="' + cmid + '"]');
    for (let i = 0; i < items.length; i++) {
        let item = items[i];
        item.addEventListener('click', checkClicked);
        if (item.classList.contains('itemoptional')) {
            checklist.optionalcount++;
            if (item.checked) {
                checklist.optionalchecked++;
            }
        } else {
            checklist.requiredcount++;
            if (item.checked) {
                checklist.requiredchecked++;
            }
        }
    }

    window.addEventListener('visibilitychange', () => {
        sendBatchUpdate(cmid, true);
    }, false);

    checklists[cmid] = checklist;
};

const checkClicked = (event) => {
    let item = event.currentTarget;
    let cmid = item.dataset.cmid;

    if (checklists[cmid].updateprogress) {
        let change = item.checked ? 1 : -1;
        if (item.classList.contains('itemoptional')) {
            checklists[cmid].optionalchecked += change;
        } else {
            checklists[cmid].requiredchecked += change;
        }
        updateProgressBar(cmid);
    }

    // Save check to list for updating
    updateServer(cmid, item.value, item.checked);
};

const updateProgressBar = (cmid) => {
    let checklist = checklists[cmid];
    let prreq = $('.checklistbox[data-cmid="' + cmid + '"] > #checklistprogressrequired');

    let allpercent = (checklist.optionalchecked + checklist.requiredchecked) * 100.0 /
        (checklist.optionalcount + checklist.requiredcount);
    let inner = $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressall .checklist_progress_inner')[0];
    let inneranim = $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressall .checklist_progress_anim')[0];
    let oldpercent = parseFloat(inner.style.width.replace('%', ''));

    if (allpercent > oldpercent) {
        inneranim.style.width = allpercent + '%';
        $(inner).animate({
            width: allpercent + '%'
        }, 1000);
    } else if (allpercent < oldpercent) {
        inner.style.width = allpercent + '%';
        $(inneranim).animate({
            width: allpercent + '%'
        }, 1000);
    }
    $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressall .checklist_progress_percent')
        .text(' ' + allpercent.toFixed(0) + '% ');

    if (prreq.length) {
        let reqpercent = checklist.requiredchecked * 100.0 / checklist.requiredcount;
        inner = $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressrequired .checklist_progress_inner')[0];
        inneranim = $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressrequired .checklist_progress_anim')[0];
        oldpercent = parseFloat(inner.style.width.replace('%', ''));

        if (reqpercent > oldpercent) {
            inneranim.style.width = reqpercent + '%';
            $(inner).animate({
                width: reqpercent + '%'
            }, 1000);
        } else if (reqpercent < oldpercent) {
            inner.style.width = reqpercent + '%';
            $(inneranim).animate({
                width: reqpercent + '%'
            }, 1000);
        }
        $('.checklistbox[data-cmid="' + cmid + '"] #checklistprogressrequired .checklist_progress_percent')
            .text(' ' + reqpercent.toFixed(0) + '% ');
    }
};

const updateServer = (cmid, itemid, state) => {
    // Remove existing update record if they are a different state.
    for (let i = 0; i < updateList.length; i++) {
        if (updateList[i].itemid === itemid) {
            if (updateList[i].state !== state) {
                updateList.splice(i, 1);
                break;
            }
            return;
        }
    }

    updateList.push({'itemid': itemid, 'state': state});

    if (updateTimeout) {
        window.clearTimeout(updateTimeout);
    }
    updateTimeout = window.setTimeout(function() {
        sendBatchUpdate(cmid, false);
    }, 500);
    showSpinner(cmid);
};

const sendBatchUpdate = (cmid, unload) => {
    if (updateTimeout) {
        window.clearTimeout(updateTimeout);
        updateTimeout = null;
    }
    if (updateList.length === 0) {
        return;
    }

    let params = [];
    for (let i = 0; i < updateList.length; i++) {
        params.push('items[' + updateList[i].itemid + ']=' + (updateList[i].state ? 1 : 0));
    }
    params.push('sesskey=' + sesskey);
    params.push('id=' + cmid);
    let url = M.cfg.wwwroot + '/mod/checklist/updatechecks.php?' + params.join('&');

    updateList = [];

    if (!unload) {
        $.ajax({
            type: "POST",
            async: true,
            url: url,
        }).then((data) => {
            hideSpinner(cmid);
            if (data !== 'OK') {
                Notification.alert('', data);
            }
            return null;
        }).fail(Notification.exception);
    } else {
        navigator.sendBeacon(url);
    }
};

const hideSpinner = (cmid) => {
    $('#checklistspinner[data-cmid="' + cmid + '"]').hide();
};

const showSpinner = (cmid) => {
    $('#checklistspinner[data-cmid="' + cmid + '"]').show();
};
