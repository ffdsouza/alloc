function makeAjaxRequest(url,entityid,extra_fields) {
  $("#"+entityid).html('<img src="../images/ticker2.gif" alt="Updating field..." title="Updating field...">');
  jQuery.get(url,extra_fields,function(data) {
    $("#"+entityid).hide();
    $("#"+entityid).html(data);
    $("#"+entityid).fadeIn("fast");
  })
}

// This is a generic show/hide for anything
function set_grow_shrink(id, id_to_hide, use_classes_instead_of_ids) {
  // toggle the other div - if any
  if (use_classes_instead_of_ids && id_to_hide) {
    $("."+id_to_hide).slideToggle("fast");
  } else if (id_to_hide) {
    $("#"+id_to_hide).slideToggle("fast");
  }
  // hide or show the actual div
  if (use_classes_instead_of_ids) {
    $("."+id).slideToggle("fast");
  } else {
    $("#"+id).slideToggle("fast");
  }
  return false;
}

function sidebyside_activate(id,arr) {
  if (id == "sbsAll") {
    for (var i=0; i<arr.length; i++) {
      if (arr[i] != "sbsAll") {
        //$("#"+arr[i]).show(); konqueror doesn't like this
        document.getElementById(arr[i]).style.display = 'inline';
        $('#sbs_link_'+arr[i]).removeClass("sidebyside_active").addClass("sidebyside");
      }
    }
    $('#sbs_link_' + id).addClass("sidebyside_active");

  } else {
    for (var i=0; i<arr.length; i++) {
      if (arr[i] != "sbsAll") {
        //$("#"+arr[i]).hide();  konqueror doesn't like this
        document.getElementById(arr[i]).style.display = 'none';
      }
      $('#sbs_link_' + arr[i]).removeClass("sidebyside_active").addClass("sidebyside");
    }
    $('#sbs_link_' + id).addClass("sidebyside_active");
    //$("#"+id).show(); konqueror doesn't like this
    document.getElementById(id).style.display = 'inline';
  }
}

function help_text_on(id, str) {
  $('#main').append("<div id='helper' style='display:none'></div>");
  $('#helper').hide().html(str).corner();
  
  offset = $('#'+id).offset();
  
  x = offset.left -400;
  if (x < 0) {
    x = x + 380;
  } 
  $("#helper").css('left',x);
  
  y = offset.top - 50;
  if (y > 350) {
    y = y-$('#helper').height() -40;
  } 
  
  $("#helper").css('top',y);
  $("#helper").fadeIn("normal");
} 
function help_text_off(id) {
  $("#helper").fadeOut("normal");
  $('#helper').remove();
} 
function preload_field(element, text) {
  $(element).bind("focus", function(e){
    if (this.value == text) {
      this.style.color = "#333333";
      this.value = "";
    }
  });
  $(element).each(function(){
    if (this.value == "") {
      this.style.color = "#bbbbbb";
      this.value = text;
    }
  });
  $('form').submit(function(){
    $(element).each(function(){
      if (this.value == text) {
        this.value = "";
      }
    });
  });
}



// Preload mouseover images
if (document.images) {
  pic1= new Image(9,11);
  pic1.src="../images/arrow_blank.gif";
  pic2= new Image(9,11);
  pic2.src="../images/arrow_faded.gif";
  pic3= new Image(9,11);
  pic3.src="../images/arrow_down.gif";
  pic4= new Image(9,11);
  pic4.src="../images/arrow_up.gif";
  pic5= new Image(119,13);
  pic5.src="../images/ticker2.gif";
}


// When the document has loaded...
$(document).ready(function() {
  // Give the tables alternating stripes
  $(".list tr:nth-child(even)").addClass("even");
  $(".list tr:nth-child(odd)").addClass("odd");
  $(".corner").corner();
  $(".delete_button").bind("click", function(e){
    return confirm("Click OK to confirm deletion.");
  });
  $(".confirm_button").bind("click", function(e){
    return confirm("Click OK to confirm.");
  });
  $("input.datefield").bind("dblclick", function(e){
    var now = new Date();
    this.value=now.getFullYear()+'-'+(now.getMonth()+1)+'-'+now.getDate();
  });

  $('tr.clickrow').bind('click',function(e){                                                                                                     
    var id = this.id.split('_')[1]; // clickrow_43242
    if (id && !$(e.target).is('input:checkbox') && !$(e.target).is('a')) {
      $('#checkbox_'+id).attr('checked',!$('#checkbox_'+id).attr('checked'));
    }
  });

  // This loads up certain textboxes with faint help text that vanishes upon focus
  preload_field("#menu_form_needle", "Enter Search...");
  preload_field("input.datefield", "YYYY-MM-DD");

  // Add resize grippies to all textareas
  $('textarea:not(.processed)').TextAreaResizer();

});


