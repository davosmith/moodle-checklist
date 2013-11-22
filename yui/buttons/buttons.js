YUI.add('moodle-mod_checklist-buttons', function(Y) {
   
  var col_clicked = function(e)
  {
    // select the added hidden value denoting the row in question      
    var id = this.getAttribute("id");
    // loop through all the select elements in the column
    Y.all('select[name*="['+id+']"').each(function () {
        // change value of option one to selected and remove it from option 0
        this.all('option').each(function () { 
            if (this.getAttribute("value") == "0") { 
                this.removeAttribute("selected"); 
            } else if (this.getAttribute("value") == "1") { 
                this.setAttribute("selected", "selected"); 
            }        
        });
    });
  };
  
  
  var row_clicked = function(e)
  {
    // select the added hidden value denoting the row in question
    var id = this.next().getAttribute("value");
    // loop through all the select elements in the row
    Y.all('select[name*="items_'+id+'"').each(function () {
        // change value of option one to selected and remove it from option 0
        this.all('option').each(function () { 
            if (this.getAttribute("value") == "0") { 
                this.removeAttribute("selected"); 
            } else if (this.getAttribute("value") == "1") { 
                this.setAttribute("selected", "selected"); 
            }        
        });
    });
  };  
  
  // simple click handlers for every added button
  M.mod_checklist = M.mod_checklist || {};
  M.mod_checklist.buttons = {
    init: function() { 
      //Y.one(".make_col_c").delegate("click", col_clicked);  
      //Y.delegate("click", col_clicked, ".make_col_c");  
	  Y.on("click", col_clicked, ".make_col_c");  
     Y.on("click", row_clicked, ".make_c");
	}
  };
});
