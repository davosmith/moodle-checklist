mod_checklist = {

    serverurl: null,
    sesskey: null,
    cmid: null,
    updatelist: null,
    updatetimeout: null,
    requiredcount: 0,
    optionalcount: 0,
    requiredchecked: 0,
    optionalchecked: 0,

    set_server: function (url, sesskey, cmid) {
	this.serverurl = url;
	this.sesskey = sesskey;
	this.cmid = cmid;
    },

    init: function() {
	var YE = YAHOO.util.Event;
	var YD = YAHOO.util.Dom;

	this.updatelist = new Array();

	var checklist = YAHOO.util.Dom.get('checklistouter');
	var items = checklist.getElementsByClassName('checklistitem');
	for (var i=0; i<items.length; i++) {
	    YE.addListener(items[i], 'click', function (e) {
		mod_checklist.check_click(this, e);
	    });
	    if (YD.hasClass(items[i], 'itemoptional')) {
		this.optionalcount++;
		if (items[i].checked) {
		    this.optionalchecked++;
		}
	    } else {
		this.requiredcount++;
		if (items[i].checked) {
		    this.requiredchecked++;
		}
	    }
	}
	YD.setStyle('checklistsavechecks', 'display', 'none');
	window.onunload =  function(e) {
	    mod_checklist.send_update_batch(true);
	};
    },

    check_click: function(el, e) {
	var YD = YAHOO.util.Dom;

	// Update progress bar
	var change = -1;
	if (el.checked) {
	    change = 1;
	}
	if (YD.hasClass(el, 'itemoptional')) {
	    this.optionalchecked += change;
	} else {
	    this.requiredchecked += change;
	}
	this.update_progress_bar();

	// Save check to list for updating
	this.update_server(el.value, el.checked);
    },

    update_progress_bar: function() {
	var YD = YAHOO.util.Dom;
	var prall = YD.get('checklistprogressall');
	var prreq = YD.get('checklistprogressrequired');

	var allpercent = (this.optionalchecked + this.requiredchecked) * 100.0 / (this.optionalcount + this.requiredcount);
	var inner = prall.getElementsByClassName('checklist_progress_inner')[0];
	YD.setStyle(inner, 'width', allpercent+'%');
	var disppercent = prall.getElementsByClassName('checklist_progress_percent')[0];
	disppercent.innerHTML = '&nbsp;'+allpercent.toFixed(0)+'% ';

	if (prreq) {
	    var reqpercent = this.requiredchecked * 100.0 / this.requiredcount;
	    var inner = prreq.getElementsByClassName('checklist_progress_inner')[0];
	    YD.setStyle(inner, 'width', reqpercent+'%');
	    var disppercent = prreq.getElementsByClassName('checklist_progress_percent')[0];
	    disppercent.innerHTML = '&nbsp;'+reqpercent.toFixed(0)+'% ';
	}

    },

    update_server: function(itemid, state) {
	for (var i=0; i<this.updatelist.length; i++) {
	    if (this.updatelist[i].itemid == itemid) {
		if (this.updatelist[i].state != state) {
		    this.updatelist.splice(i, 1);
		}
		return;
	    }
	}

	this.updatelist.push({'itemid':itemid, 'state':state});

	if (this.updatetimeout) {
	    clearTimeout(this.updatetimeout);
	}
	this.updatetimeout = setTimeout(function() {
	    mod_checklist.send_update_batch(false);
	}, 1000);
    },

    send_update_batch: function(unload) {
	// Send all updates after 1 second of inactivity (or on page unload)
	if (this.updatetimeout) {
	    clearTimeout(this.updatetimeout);
	    this.updatetimeout = null;
	}

	if (this.updatelist.length == 0) {
	    return;
	}

	var params = new Array();
	for (var i=0; i<this.updatelist.length; i++) {
	    var val = this.updatelist[i].state ? 1 : 0;
	    params.push('items['+this.updatelist[i].itemid+']='+val);
	}
	params.push('sesskey='+this.sesskey);
	params.push('id='+this.cmid);
	params = params.join('&');

	// Clear the list of updates to send
	this.updatelist = new Array();

	// Send message to server
	if (!unload) {
	    var callback= {
		success: function(o) {
		    if (o.responseText != 'OK') {
			alert(o.responseText);
		    }
		},
		failure: function(o) {
		    alert(o.statusText);
		},
		timeout: 5000
	    };

	    var YC = YAHOO.util.Connect;
	    YC.asyncRequest('POST', this.serverurl, callback, params);
	} else {
	    // Nasty hack to make it save everything on unload
	    var beacon = new Image();
	    beacon.src = this.serverurl + '?' + params;
	}
    }
}

YAHOO.util.Event.onDOMReady(function() { mod_checklist.init() });
