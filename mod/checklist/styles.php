/* -*-CSS-*- */

ol.checklist li {
    list-style-type: none;
}

ol.checklist .useritem {
    font-style: italic;
    color: #404090;
}

ol.checklist .note {
    font-style: italic;
    color: #a0a0e0;
    padding: 0 0 0 20px;
}

ol.checklist .itemoptional {
    font-style: italic;
}

ol.checklist .itemheading {
    font-weight: bold;
}

ol.checklist .itemblack {
    color: #000000;
}

ol.checklist .itemblack.itemoptional {
    color: #a0a0a0;
}

ol.checklist .itemred {
    color: #ff0000;
}

ol.checklist .itemred.itemoptional {
    color: #ffa0a0;
}

ol.checklist .itemorange {
    color: #ffba00;
}

ol.checklist .itemorange.itemoptional {
    color: #ffdaa0;
}

ol.checklist .itemgreen {
    color: #00ff00;
}

ol.checklist .itemgreen.itemoptional {
    color: #a0ffa0;
}

ol.checklist .itempurple {
    color: #d000ff;
}

ol.checklist .itempurple.itemoptional {
    color: #d0a0ff;
}

ol.checklist .teachercomment {
    color: black;
    background-color: #ffffb0;
    border: solid black 1px;
    margin: 0 0 0 20px;
}

ol.checklist .itemauto.itemdisabled {
    text-decoration: line-through;
    background-color: #bcc4c4;
}

ol.checklist .itemauto {
    background-color: #d6e6e7;
}

ol.checklist li .itemuserdate {
    background-color: #b0ffb0;
    border: solid black 1px;	
    position: absolute;
	margin: 0 0 0 20px;
}

ol.checklist li .itemteacherdate {
    background-color: #b0ffb0;
	border: solid black 1px;
    margin: 0 0 0 10px;
}

ol.checklist li .itemcheckedbyteacher {
    background-color: #b0ffb0;
	border: solid black 1px;
    margin: 0 0 0 10px;
}

.itemdue {
    font-style: italic;
    color: #90d090;
}

.itemoverdue {
    font-style: italic;
    color: #f09090;
}

.checklistreport .header {
    background-color: #e1e1df;
}

.checklistreport .head0 {
    font-weight: bold;
}

.checklistreport .head1 {
    font-weight: normal;
}

.checklistreport .head2 {
    font-weight: normal;
    font-style: italic;
}

.checklistreport .footer {
    font-color: #ff0000;
}

.checklistreport .reportheading {
    background-color: #000000;
}

.checklistreport .level0 {
    background-color: #e7e7e7;
}

.checklistreport .level1 {
    background-color: #c7c7c7;
}

.checklistreport .level2 {
    background-color: #afafaf;
}

.checklistreport .level0-checked {
    background-color: #00ff00;
}

.checklistreport .level1-checked {
    background-color: #00df00;
}

.checklistreport .level2-checked {
    background-color: #00bf00;
}

.checklistreport .level0-unchecked {
    background-color: #ff0000;
}

.checklistreport .level1-unchecked {
    background-color: #df0000;
}

.checklistreport .level2-unchecked {
    background-color: #bf0000;
}

.checklist_progress_outer {
    border-width: 1px;
    border-style: solid;
    border-color: black;
    width: 300px;
    background-color: transparent;
    height: 15px;
    float: left;
    overflow: clip;
    position: relative;
}

.checklist_progress_inner {
    background-color: #229b15;
    height: 100%;
    width: 100%;
    background-repeat: repeat-x;
    background-position: top;
    float: left;
}

.checklist_progress_anim {
    background-color: #98c193;
    height: 15px;
    width: 0;
    background-repeat: repeat-x;
    background-position: top;
    position: absolute;
    z-index: -1;
    left: 0;
    top: 0;
}

.checklistimportexport {
    text-align: right;
    width: 90%;
}

.checklistwarning {
    margin-top: 1em;
    color: #800000;
}
