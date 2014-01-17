YUI.add('moodle-mod_checklist-buttons', function(Y) {

  var col_clicked = function(e) {
    // use the 1st value in the toggle array to toggle through states
    col_toggle_id = column_toggle_values.shift();
    // select the added hidden value denoting the row in question
    var id = this.getAttribute("id");
    // loop through all the select elements in the column
    Y.all('select[name*="['+id+']"').each(function () {
        // loop through all the option tags in the select dropdown
        this.all('option').each(function () {
            if (this.getAttribute("value") != col_toggle_id) {
                // remove the selected attribute from all option tags that are NOT the toggle value
                this.removeAttribute("selected"); 
            } else {
                this.setAttribute("selected", "selected");
            }
        });
    });
    // place removed toggle value at the end of the toggle array
    column_toggle_values.push(col_toggle_id);
  };

  var row_clicked = function(e) {
    // use the 1st value in the toggle array to toggle through states
    row_toggle_id = row_toggle_values.shift();
    // select the added hidden value denoting the row in question
    var id = this.getAttribute("id");
    // loop through all the select elements in the row
    Y.all('select[name*="items_'+id+'"').each(function () {
        // loop through all the option tags in the select dropdown
        this.all('option').each(function () { 
            if (this.getAttribute("value") != row_toggle_id) {
                // remove the selected attribute from all option tags that are NOT the toggle value
                this.removeAttribute("selected");
            } else {
                this.setAttribute("selected", "selected");
            }
        });
    });
    // place removed toggle value at the end of the toggle array    
    row_toggle_values.push(row_toggle_id);
  };

    // simple click handlers for every added button

    M.mod_checklist = M.mod_checklist || {};
    M.mod_checklist.buttons = {
        init: function() {
            Y.on("click", col_clicked, ".make_col_c");
            Y.on("click", row_clicked, ".make_c");
            column_toggle_values = Array(0, 1, 2);
            row_toggle_values = Array(1, 2, 0);
        }
    };
});
