/*YUI().use('dom', 'event', 'connection', 'animation', 'easing', function(Y) {*/
    M.mod_checklist = {

	serverurl: null,
	sesskey: null,
	cmid: null,
	updateprogress: 1,
	updatelist: null,
	updatetimeout: null,
	requiredcount: 0,
	optionalcount: 0,
	requiredchecked: 0,
	optionalchecked: 0,
	anim1: null,
	anim2: null,

	init: function(Y, url, sesskey, cmid, updateprogress) {
	    this.serverurl = url;
	    this.sesskey = sesskey;
	    this.cmid = cmid;
	    this.updateprogress = updateprogress;

	    var YE = YAHOO.util.Event;
	    var YD = YAHOO.util.Dom;

	    this.updatelist = new Array();
	    var items = YD.getElementsByClassName('checklistitem');
	    for (var i=0; i<items.length; i++) {
		YE.addListener(items[i], 'click', function (e) {
		    M.mod_checklist.check_click(this, e);
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
		M.mod_checklist.send_update_batch(true);
	    };
	},

	check_click: function(el, e) {
	    var YD = YAHOO.util.Dom;

	    // Update progress bar
	    if (this.updateprogress) {
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
	    }

	    // Save check to list for updating
	    this.update_server(el.value, el.checked);
	},

	startanim: function(number, ya) {
	    if (number == 1) {
		if (this.anim1) {
		    this.anim1.stop();
		}
		this.anim1 = ya;
		this.anim1.animate();
	    } else if (number == 2) {
		if (this.anim2) {
		    this.anim2.stop();
		}
		this.anim2 = ya;
		this.anim2.animate();
	    }
	},

	update_progress_bar: function() {
	    var YD = YAHOO.util.Dom;
	    var YA = YAHOO.util.Anim;
	    var YE = YAHOO.util.Easing.easeOut;
	    var prall = YD.get('checklistprogressall');
	    var prreq = YD.get('checklistprogressrequired');

	    var allpercent = (this.optionalchecked + this.requiredchecked) * 100.0 / (this.optionalcount + this.requiredcount);
	    var inner = YD.getElementsByClassName('checklist_progress_inner', 'div', prall)[0];
	    var inneranim = YD.getElementsByClassName('checklist_progress_anim', 'div', prall)[0];
	    var oldpercent = parseFloat(YD.getStyle(inner, 'width').replace("%",""));
	    if (allpercent > oldpercent) {
		YD.setStyle(inneranim, 'width', allpercent+'%');
		this.startanim(1, new YA(inner, { width: { from: oldpercent, to: allpercent, unit: '%' } }, 1, YE));
	    } else if (allpercent < oldpercent) {
		YD.setStyle(inner, 'width', allpercent+'%');
		var oldanimpercent = parseFloat(YD.getStyle(inneranim, 'width').replace("%",""));
		this.startanim(1, new YA(inneranim, { width: { from: oldanimpercent, to: allpercent, unit: '%' } }, 1, YE));
	    }
	    var disppercent = YD.getElementsByClassName('checklist_progress_percent', 'span', prall)[0];
	    disppercent.innerHTML = '&nbsp;'+allpercent.toFixed(0)+'% ';

	    if (prreq) {
		var reqpercent = this.requiredchecked * 100.0 / this.requiredcount;
		var inner = YD.getElementsByClassName('checklist_progress_inner', 'div', prreq)[0];
		var inneranim = YD.getElementsByClassName('checklist_progress_anim', 'div', prreq)[0];
		var oldpercent = parseFloat(YD.getStyle(inner, 'width').replace("%",""));
		if (reqpercent > oldpercent) {
		    YD.setStyle(inneranim, 'width', reqpercent+'%');
		    this.startanim(2, new YA(inner, { width: { from: oldpercent, to: reqpercent, unit: '%' } }, 1, YE));
		} else if (reqpercent < oldpercent) {
		    YD.setStyle(inner, 'width', reqpercent+'%');
		    var oldanimpercent = parseFloat(YD.getStyle(inneranim, 'width').replace("%",""));
		    this.startanim(2, new YA(inneranim, { width: { from: oldanimpercent, to: reqpercent, unit: '%' } }, 1, YE));
		}

		var disppercent = YD.getElementsByClassName('checklist_progress_percent', 'span', prreq)[0];
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
		M.mod_checklist.send_update_batch(false);
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
//});

