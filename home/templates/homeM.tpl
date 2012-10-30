{page::header()}
  {page::toolbar()}
  <div style="float:left; width:70%; vertical-align:top; padding:0; margin:0px; margin-right:1%; min-width:400px;">
    {show_home_items("standard",$home_items)}
  </div>
  <div style="float:left; width:29%; vertical-align:top; padding:0; margin:0px;">
    {show_home_items("narrow",$home_items)}
  </div>


  <!-- hidden preferences options. -->
  <div class="config_top_ten_tasks hidden config-pane lazy">
  </div>


  <div class="config_task_calendar_home_item hidden config-pane">
  <form action="{$url_alloc_home}" method="post">
  <div>
    <h6>Calendar Weeks<div>Weeks Back</div></h6> 
    <div style="float:left; width:30%;">
      <select name="weeks">{$weeksOptions}</select>
      {page::help("<b>Calendar Weeks</b><br><br>Control the number of weeks that the home page calendar displays.")}
    </div>
    <div style="float:right; width:50%;">
      <select name="weeksBack">{$weeksBackOptions}</select>
      {page::help("<b>Weeks Back</b><br><br>Control how many weeks in arrears are displayed on the home page calendar.")}
    </div>
  </div>
  <br><br>
  <span style="float:right">
    <a href="#x" onClick="$(this).parent().parent().parent().fadeOut();">Cancel</a>
    <button type="submit" name="customize_save" value="1" class="save_button">Save<i class="icon-ok-sign"></i></button>
  </span>
  <input type="hidden" name="sessID" value="{$sessID}">
  </form>
  </div>


  <div class="config_project_list hidden config-pane">
  <form action="{$url_alloc_home}" method="post">
  <div>
    <h6>Project List</h6> 
    <div style="float:left; width:30%;">
      <select name="projectListNum">{$projectListNumOptions}</select>
      {page::help("<b>Project List</b><br><br>Control the number of projects displayed on your home page.")}
    </div>
  </div>
  <br><br>
  <span style="float:right">
    <a href="#x" onClick="$(this).parent().parent().parent().fadeOut();">Cancel</a>
    <button type="submit" name="customize_save" value="1" class="save_button">Save<i class="icon-ok-sign"></i></button>
  </span>
  <input type="hidden" name="sessID" value="{$sessID}">
  </form>
  </div>

  <div class="config_time_list hidden config-pane">
  <form action="{$url_alloc_home}" method="post">
    <div>
      <h6>Time Sheet Hours<div>Time Sheet Days</div></h6> 
      <div style="float:left; width:30%;">
        <input type="text" size="5" name="timeSheetHoursWarn" value="{$timeSheetHoursWarn}">
        {page::help("<b>Time Sheet Hours</b><br><br>Time sheets that go over this number of hours and are still in edit status will be flagged for you.")}
      </div>
      <div style="float:right; width:50%;">
        <input type="text" size="5" name="timeSheetDaysWarn" value="{$timeSheetDaysWarn}">
        {page::help("<b>Time Sheet Days</b><br><br>Time sheets that are older than this many days and are still in edit status will be flagged for you.")}
      </div>
    </div>
  <br><br>
  <span style="float:right">
    <a href="#x" onClick="$(this).parent().parent().parent().fadeOut();">Cancel</a>
    <button type="submit" name="customize_save" value="1" class="save_button">Save<i class="icon-ok-sign"></i></button>
  </span>
  <input type="hidden" name="sessID" value="{$sessID}">
  </form>
  </div>


  <div class="config_time_status_list hidden config-pane">
  <form action="{$url_alloc_home}" method="post">
    <div>
      <h6>Time Sheet Stats<div></div></h6> 
      <div style="float:left; width:30%;">
        <select name="showTimeSheetStats">{page::select_options(array(0=>"No",1=>"Yes"),$showTimeSheetStats)}</select>
        {page::help("<b>Show Time Sheet Stats</b><br><br>Show the time sheet stats box on the homepage.")}
      </div>
    </div>
  <br><br>
  <span style="float:right">
    <a href="#x" onClick="$(this).parent().parent().parent().fadeOut();">Cancel</a>
    <button type="submit" name="customize_save" value="1" class="save_button">Save<i class="icon-ok-sign"></i></button>
  </span>
  <input type="hidden" name="sessID" value="{$sessID}">
  </form>
  </div>


  <div class="config_time_edit hidden config-pane">
  <form action="{$url_alloc_home}" method="post">
    <div>
      <h6>New Time Sheet Item<div></div></h6> 
      <div style="float:left; width:30%;">
        <select name="showNewTimeSheetItem">{page::select_options(array(0=>"No",1=>"Yes"),$showNewTimeSheetItem)}</select>
        {page::help("<b>Show New Time Sheet Item</b><br><br>Show the new time sheet item input on the homepage.")}
      </div>
    </div>
  <br><br>
  <span style="float:right">
    <a href="#x" onClick="$(this).parent().parent().parent().fadeOut();">Cancel</a>
    <button type="submit" name="customize_save" value="1" class="save_button">Save<i class="icon-ok-sign"></i></button>
  </span>
  <input type="hidden" name="sessID" value="{$sessID}">
  </form>
  </div>







{page::footer()}
