<?php

/*
 * Copyright (C) 2006, 2007, 2008 Alex Lance, Clancy Malcolm, Cybersource
 * Pty. Ltd.
 * 
 * This file is part of the allocPSA application <info@cyber.com.au>.
 * 
 * allocPSA is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at
 * your option) any later version.
 * 
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public
 * License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with allocPSA. If not, see <http://www.gnu.org/licenses/>.
*/

define("PERM_PROJECT_READ_TASK_DETAIL", 256);

class task extends db_entity {
  public $classname = "task";
  public $data_table = "task";
  public $display_field_name = "taskName";
  public $key_field = "taskID";
  public $data_fields = array("taskName" => array("audit"=>true)
                             ,"taskDescription" => array("audit"=>true)
                             ,"creatorID"
                             ,"closerID"
                             ,"priority" => array("audit"=>true)
                             ,"timeEstimate" => array("audit"=>true)
                             ,"dateCreated"
                             ,"dateAssigned"
                             ,"dateClosed"
                             ,"dateTargetStart" => array("audit"=>true)
                             ,"dateTargetCompletion" => array("audit"=>true)
                             ,"dateActualStart" => array("audit"=>true)
                             ,"dateActualCompletion" => array("audit"=>true)
                             ,"taskComments"
                             ,"taskStatus"
                             ,"taskModifiedUser"
                             ,"taskSubStatus" => array("audit"=>true)
                             ,"projectID" => array("audit"=>true)
                             ,"parentTaskID" => array("audit"=>true)
                             ,"taskTypeID" => array("audit"=>true)
                             ,"personID" => array("audit"=>true)
                             ,"managerID" => array("audit"=>true)
                             ,"duplicateTaskID" => array("audit"=>true)
                             );
  public $permissions = array(PERM_PROJECT_READ_TASK_DETAIL => "read details");

  function save() {
    global $current_user, $TPL;
    if (!$this->get_value("creatorID")) {
      $this->set_value("creatorID",$current_user->get_id());
    }

    // Change a task status that might look like: open_notstarted into taskStatus:open and taskSubStatus:notstarted
    if ($this->get_value("taskStatus") && preg_match("/_/",$this->get_value("taskStatus"))) {
      list($taskStatus, $taskSubStatus) = explode("_",$this->get_value("taskStatus"));
      $this->set_value("taskStatus",$taskStatus);
      $this->set_value("taskSubStatus",$taskSubStatus);
    } else if (!$this->get_value("taskStatus")) {
      $this->set_value("taskStatus","open");
      $this->set_value("taskSubStatus","notstarted");
    }

    // Wipe the task.duplicateTaskID field if this isn't a duplicated task ...
    if ($this->get_value("taskSubStatus") != "duplicate") {
      $this->set_value("duplicateTaskID", "");
    }


    // Marked as dupe?
    if ($this->get_value("duplicateTaskID") && $this->get_value("duplicateTaskID") != $this->all_row_fields["duplicateTaskID"]) {

      $othertask = new task;
      $othertask->set_id($this->get_value("duplicateTaskID"));
      $othertask->select();
      if ($othertask->get_value("duplicateTaskID")) {
        $TPL["message"][] = "Task ".$this->get_value("duplicateTaskID")." ".$othertask->get_name()." is a duplicate. 
                             Task may not be a duplicate of a duplicate.";
        alloc_redirect($TPL["url_alloc_task"]."taskID=".$this->get_id());
      }
      if ($othertask->get_id() == $this->get_id() || $othertask->get_id() == 0) {
        $TPL["message"][] = "Error setting duplicate. Invalid Task ID.";
        alloc_redirect($TPL["url_alloc_task"]."taskID=".$this->get_id());
      } 

      $this->set_value("duplicateTaskID", $this->get_value("duplicateTaskID"));
      
      // Note in the other task's history that this task was marked a duplicate of it
      $ai = new auditItem;
      $ai->audit_special_change($othertask, "TaskMarkedDuplicate", $this->get_id());
      $ai->insert();
      if ($this->all_row_fields["duplicateTaskID"]) {
        // and, if we have a previous duplicate, note in that one that it's no longer a duplicate
        $othertask = new task;
        $othertask->set_id($this->all_row_fields["duplicateTaskID"]);
        $othertask->select();
        $ai = new auditItem;
        $ai->audit_special_change($othertask, "TaskUnmarkedDuplicate", $this->get_id());
        $ai->insert();
      } 
      // if dupe, close off the task
      $this->set_value("taskStatus","closed");
      $this->set_value("taskSubStatus","duplicate");
    } 


    // If the tasks status has just moved to closed, close() the task.
    if (!$this->has_just_been_closed && $this->get_value("taskStatus") == "closed" && $this->all_row_fields["taskStatus"] != "closed") {
      $this->close();

    // Else if it was closed, and it has now been re-opened
    } else if (!$this->has_just_been_opened && $this->all_row_fields["taskStatus"] == "closed" && $this->get_value("taskStatus") != "closed" ) {
      $this->open();
    
    // if they have just set a dateActualCompletion, mark all children as
    // complete. (the $this->all_row_fields contains the *original* values)
    } else if (!$this->has_just_been_closed && $this->get_value("dateActualCompletion") && !$this->all_row_fields["dateActualCompletion"]) {
      $this->close();

    // Else if there was a dateActualCompletion and they have just *unset* it...
    } else if (!$this->has_just_been_opened && $this->all_row_fields["dateActualCompletion"] && !$this->get_value("dateActualCompletion")) {
      $this->open();
    }

    // If there is no dateActualStart, and the task status has just been changed to In Progress, then set the dateActualStart
    if (!$this->get_value("dateActualStart") 
    && $this->all_row_fields["taskSubStatus"] != "inprogress" && $this->get_value("taskSubStatus") == "inprogress") {
      $this->set_value("dateActualStart",date("Y-m-d"));
    
    // If they've just plugged a dateActualStart in and the task is not closed, then change the status to Open: In Progress
    } else if (!$this->all_row_fields["dateActualStart"] && $this->get_value("dateActualStart") && $this->get_value("taskStatus") != "closed") {
      $this->set_value("taskStatus","open");
      $this->set_value("taskSubStatus","inprogress");
    }

    // If task exists and the personID has changed, update the dateAssigned
    if ($this->get_id()) {
      if (sprintf("%d",$this->get_value("personID")) != sprintf("%d",$this->all_row_fields["personID"])) {
        $this->set_value("dateAssigned",date("Y-m-d H:i:s"));
      }
    // Else if task doesn't exist and there is a personID set, set the dateAssigned as well
    } else if ($this->get_value("personID")) {
      $this->set_value("dateAssigned",date("Y-m-d H:i:s"));
    }

    $this->get_value("taskDescription") and $this->set_value("taskDescription",rtrim($this->get_value("taskDescription")));

    $rtn = parent::save();

    // If the task has just been closed, then audit the change.
    if ($this->has_just_been_closed) {
      $this->mark_closed();
    }

    return $rtn;
  }

  function validate() {
    $this->get_value("taskName") or $err[] = "Please enter a name for the Task.";
    return $err;
  }
  
  function close($taskSubStatus = "complete") {
    global $current_user;
    $this->get_value("dateActualStart")      || $this->set_value("dateActualStart", date("Y-m-d"));
    $this->get_value("dateActualCompletion") || $this->set_value("dateActualCompletion", date("Y-m-d"));
    $this->get_value("closerID")             || $this->set_value("closerID", $current_user->get_id());
    $this->get_value("dateClosed")           || $this->set_value("dateClosed",date("Y-m-d H:i:s"));           
    if ($this->get_value("taskStatus") != "closed") {
      $this->set_value("taskStatus","closed");
      $this->set_value("taskSubStatus",$taskSubStatus);
    }

    if ($this->get_value("taskTypeID") == "Parent") {
      $this->close_off_children_recursive();
    }
    $this->has_just_been_closed = true;
  }

  function open($taskSubStatus = "inprogress") {
    $this->set_value("closerID",null);
    $this->set_value("dateClosed","");
    $this->set_value("dateActualCompletion","");
    if ($this->get_value("taskStatus") != "open") {
      $this->set_value("taskStatus","open");
      $this->set_value("taskSubStatus",$taskSubStatus);
    }
    $this->mark_reopened();
    $this->has_just_been_opened = true;
  }

  function close_off_children_recursive() {
    // mark all children as complete
    global $current_user;
    $db = new db_alloc;
    if ($this->get_id()) {
      $query = sprintf("SELECT * FROM task WHERE parentTaskID = %d",$this->get_id());
      $db->query($query);
                                                                                                                               
      while ($db->next_record()) {
        $task = new task;
        $task->read_db_record($db);
        $task->close();
        $task->save();
      }
    }
  }

  function create_task_reminder() {
    // Create a reminder for this task based on the priority.
    global $current_user;

    // Get the task type
    $taskTypeName = $this->get_value("taskTypeID");
    $label = $this->get_priority_label();
    $reminderInterval = "Day";
    $intervalValue = $this->get_value("priority");
    $taskTypeName == "Parent" and $taskTypeName.= " Task";

    $subject = $taskTypeName." Reminder: ".$this->get_id()." ".$this->get_name()." [".$label."]";
    $message = "\n\n".$subject;
    $message.= "\n\n".$this->get_url(true);
    $this->get_value("taskDescription") and $message.= "\n\n".$this->get_value("taskDescription");
    $message.= "\n\n-- \nReminder created by ".$current_user->get_username(1)." at ".date("Y-m-d H:i:s");
    $people[] = $this->get_value("personID");
    $this->create_reminder(null, $message, $reminderInterval, $intervalValue, REMINDER_METAPERSON_TASK_ASSIGNEE, $subject);
  }

  function create_reminders($people, $message, $reminderInterval, $intervalValue) {
    if (is_array($people)) {
      foreach($people as $personID) {
        $person = new person;
        $person->set_id($personID);
        $person->select();
        if ($person->get_value("emailAddress")) {
          $this->create_reminder($personID, $message, $reminderInterval, $intervalValue);
        }
      }
    }
  }

  function create_reminder($personID=null, $message, $reminderInterval, $intervalValue, $metaPerson=null, $subject="") {
    $label = $this->get_priority_label();

    $reminder = new reminder;
    $reminder->set_value('reminderType', "task");
    $reminder->set_value('reminderLinkID', $this->get_id());
    $reminder->set_value('reminderRecuringInterval', $reminderInterval);
    $reminder->set_value('reminderRecuringValue', $intervalValue);
    $reminder->set_value('reminderSubject', $subject);
    $reminder->set_value('reminderContent', $message);

    $reminder->set_value('reminderAdvNoticeSent', "0");
    $reminder->set_value('reminderAdvNoticeInterval', "No");
    $reminder->set_value('reminderAdvNoticeValue', "0");

    $reminder->set_value('reminderTime', date("Y-m-d H:i:s"));
    if ($personID) {
      $reminder->set_value('personID', $personID);
    } else if ($metaPerson) {
      $reminder->set_value('metaPerson', $metaPerson);
    }
    $reminder->save();
  }

  function is_owner($person = "") {
    // A user owns a task if the 'own' the project
    if ($this->get_id()) {
      // Check for existing task
      $p = $this->get_foreign_object("project");
    } else if ($_POST["projectID"]) {
      // Or maybe they are creating a new task
      $p = new project;
      $p->set_id($_POST["projectID"]);
    }

    // if this task doesn't exist (no ID) 
    // OR the person has isManager or canEditTasks for this tasks project 
    // OR if this person is the Creator of this task.
    // OR if this person is the For Person of this task.
    // OR if this person has super 'manage' perms
    // OR if we're skipping the perms checking because i.e. we're having our task status updated by a timesheet
    if (
       !$this->get_id() 
    || (is_object($p) && ($p->has_project_permission($person, array("isManager", "canEditTasks"))) 
    || $this->get_value("creatorID") == $person->get_id()
    || $this->get_value("personID") == $person->get_id()
    || $person->have_role("manage")
    || $this->skip_perms_check
    )) {
      return true;
    }
  }

  function has_attachment_permission($person) {
    return $this->is_owner($person);
  }

  function has_attachment_permission_delete($person) {
    return $this->is_owner($person);
  }

  function update_children($field,$value="") {
    $q = sprintf("SELECT * FROM task WHERE parentTaskID = %d",$this->get_id());
    $db = new db_alloc();
    $db->query($q);
    while ($db->row()) {
      $t = new task;
      $t->read_db_record($db);
      $t->set_value($field,$value);
      $t->save();
      if ($t->get_value("taskTypeID") == "Parent") {
        $t->update_children($field,$value);
      }
    }
  }

  function get_parent_task_select($projectID="") {
    global $TPL;
    
    if (is_object($this)) {
      $projectID = $this->get_value("projectID");
      $parentTaskID = $this->get_value("parentTaskID");
    }

    $projectID or $projectID = $_GET["projectID"];
    $parentTaskID or $parentTaskID = $_GET["parentTaskID"];

    $db = new db_alloc;
    if ($projectID) {
      $query = sprintf("SELECT taskID AS value, taskName AS label
                        FROM task 
                        WHERE projectID= '%d' 
                        AND taskTypeID = 'Parent'
                        AND (taskStatus != 'closed' or taskID = %d)
                        ORDER BY taskName", $projectID, $parentTaskID);
      $options = page::select_options($query, $parentTaskID,70);
    }
    return "<select name=\"parentTaskID\"><option value=\"\">".$options."</select>";
  }

  function get_task_cc_list_select($projectID="") {
    $interestedParty = array();
    $interestedPartyOptions = array();
    
    if (is_object($this)) {
      $interestedPartyOptions = $this->get_all_task_parties($projectID);
    } else {
      $interestedPartyOptions = task::get_all_task_parties($projectID);
    }

    #echo "<pre>".print_r($interestedPartyOptions,1)."</pre>";
  
    if (is_array($interestedPartyOptions)) {

      foreach ($interestedPartyOptions as $email => $info) {
        $name = $info["name"];
        $identifier = $info["identifier"];

        if ($info["role"] == "interested" && $info["selected"]) {
          $interestedParty[] = $identifier;
        }

        if ($email) {
          $name = trim($name);
          $str = trim(page::htmlentities($name." <".$email.">"));
          $options[$identifier] = $str;
        }
      }
    }
    $str = "<select name=\"interestedParty[]\" size=\"6\" multiple=\"true\"  style=\"width:95%\">".page::select_options($options,$interestedParty,100,false)."</select>";
    return $str;
  }

  function get_all_task_parties($projectID="") {
    $db = new db_alloc;
    $interestedPartyOptions = array();
  
    if ($_GET["projectID"]) {
      $projectID = $_GET["projectID"];
    } else if (!$projectID && is_object($this)) {
      $projectID = $this->get_value("projectID");
    }

    if ($projectID) {
      // Get primary client contact from Project page
      $q = sprintf("SELECT projectClientName,projectClientEMail FROM project WHERE projectID = %d",$projectID);
      $db->query($q);
      $db->next_record();
      $interestedPartyOptions[$db->f("projectClientEMail")] = array("name"=>$db->f("projectClientName"),"external"=>"1");
  
      // Get all other client contacts from the Client pages for this Project
      $q = sprintf("SELECT clientID FROM project WHERE projectID = %d",$projectID);
      $db->query($q);
      $db->next_record();
      $clientID = $db->f("clientID");
      $q = sprintf("SELECT clientContactName, clientContactEmail, clientContactID 
                      FROM clientContact 
                     WHERE clientID = %d",$clientID);
      $db->query($q);
      while ($db->next_record()) {
        $interestedPartyOptions[$db->f("clientContactEmail")] = array("name"=>$db->f("clientContactName"),"external"=>"1","clientContactID"=>$db->f("clientContactID"));
      }

      // Get all the project people for this tasks project
      $q = sprintf("SELECT emailAddress, firstName, surname, person.personID, username
                     FROM projectPerson 
                LEFT JOIN person on projectPerson.personID = person.personID 
                    WHERE projectPerson.projectID = %d AND person.personActive = 1 ",$projectID);
      $db->query($q);
      while ($db->next_record()) {
        unset($name);
        $db->f("firstName") && $db->f("surname") and $name = $db->f("firstName")." ".$db->f("surname");
        $name or $name = $db->f("username");
        $interestedPartyOptions[$db->f("emailAddress")] = array("name"=>$name,"personID"=>$db->f("personID"));
      }
    }

    $extra_interested_parties = config::get_config_item("defaultInterestedParties") or $extra_interested_parties=array();
    foreach ($extra_interested_parties as $name => $email) {
      $interestedPartyOptions[$email] = array("name"=>$name);
    }

    if (is_object($this)) {
      if ($this->get_value("creatorID")) {
        $p = new person;
        $p->set_id($this->get_value("creatorID"));
        $p->select();
        $p->get_value("emailAddress") and $interestedPartyOptions[$p->get_value("emailAddress")] = array("name"=>$p->get_value("firstName")." ".$p->get_value("surname"), "role"=>"creator", "personID"=>$this->get_value("creatorID"));
      }
      if ($this->get_value("personID")) {
        $p = new person;
        $p->set_id($this->get_value("personID"));
        $p->select();
        $p->get_value("emailAddress") and $interestedPartyOptions[$p->get_value("emailAddress")] = array("name"=>$p->get_value("firstName")." ".$p->get_value("surname"), "role"=>"assignee", "selected"=>true, "personID"=>$this->get_value("personID"));
      }
      if ($this->get_value("managerID")) {
        $p = new person;
        $p->set_id($this->get_value("managerID"));
        $p->select();
        $p->get_value("emailAddress") and $interestedPartyOptions[$p->get_value("emailAddress")] = array("name"=>$p->get_value("firstName")." ".$p->get_value("surname"), "role"=>"manager", "selected"=>true, "personID"=>$this->get_value("managerID"));
      }
      $this_id = $this->get_id();
    }
    // return an aggregation of the current task/proj/client parties + the existing interested parties
    $interestedPartyOptions = interestedParty::get_interested_parties("task",$this_id,$interestedPartyOptions);
    return $interestedPartyOptions;
  }

  function get_encoded_interested_party_identifier($info=array()) {
    return urlencode(base64_encode(serialize($info)));
  }

  function get_decoded_interested_party_identifier($blob) {
    return unserialize(base64_decode(urldecode($blob)));
  }

  function get_personList_dropdown($projectID,$taskID=false) {
    global $current_user;
 
    $db = new db_alloc;

    if ($_GET["timeSheetID"]) {
      $ts_query = sprintf("SELECT * FROM timeSheet WHERE timeSheetID = %d",$_GET["timeSheetID"]);
      $db->query($ts_query);
      $db->next_record();
      $owner = $db->f("personID");

    } else if (is_object($this) && $this->get_value("personID")) {
      $owner = $this->get_value("personID");

    } else if ($taskID) {
      $t = new task;
      $t->set_id($taskID);
      $t->select();
      $owner = $t->get_value("personID");

    } else if (!is_object($this) || !$this->get_id()) {
      $owner = $current_user->get_id();
    }

    $peoplenames = person::get_username_list($owner);

    if ($projectID) {
      $q = sprintf("SELECT * 
                      FROM projectPerson 
                 LEFT JOIN person ON person.personID = projectPerson.personID 
                     WHERE person.personActive = 1 
                       AND projectID = %d
                  ORDER BY firstName, username
                   ",$projectID);
      $db->query($q);
      while ($row = $db->row()) {
        $ops[$row["personID"]] = $peoplenames[$row["personID"]];
      }
    } else {
      $ops = $peoplenames;
    }

    $ops[$owner] or $ops[$owner] = $peoplenames[$owner];
   
    $str = page::select_options($ops, $owner);
    return $str;
  }

  function get_managerPersonList_dropdown($projectID,$taskID=false) {
    global $current_user;
 
    $db = new db_alloc;

    if ($_GET["timeSheetID"]) {
      $ts_query = sprintf("SELECT * FROM timeSheet WHERE timeSheetID = %d",$_GET["timeSheetID"]);
      $db->query($ts_query);
      $db->next_record();
      $owner = $db->f("personID");

    } else if (is_object($this) && $this->get_value("managerID")) {
      $owner = $this->get_value("managerID");

    } else if ($taskID) {
      $t = new task;
      $t->set_id($taskID);
      $t->select();
      $owner = $t->get_value("managerID");

    } else if (!is_object($this) || !$this->get_id()) {
      $owner = $current_user->get_id();
    }

    $peoplenames = person::get_username_list($owner);

    if ($projectID) {
      $q = sprintf("SELECT * 
                      FROM projectPerson 
                 LEFT JOIN person ON person.personID = projectPerson.personID 
                     WHERE person.personActive = 1 
                       AND projectID = %d
                  ORDER BY firstName, username
                   ",$projectID);
      $db->query($q);
      while ($row = $db->row()) {
        $ops[$row["personID"]] = $peoplenames[$row["personID"]];
      }
    } else {
      $ops = $peoplenames;
    }

    $ops[$owner] or $ops[$owner] = $peoplenames[$owner];
   
    $str = '<select name="managerID"><option value="">';
    $str.= page::select_options($ops, $owner);
    $str.= '</select>';
    return $str;
  }
  
  function get_project_options($projectID="") {
    $projectID or $projectID = $_GET["projectID"];
    // Project Options - Select all projects 
    $db = new db_alloc;
    $query = sprintf("SELECT projectID AS value, projectName AS label 
                        FROM project 
                       WHERE projectStatus IN ('current', 'potential') 
                    ORDER BY projectName");
    $str = page::select_options($query, $projectID, 60);
    return $str;
  }

  function set_option_tpl_values() {
    // Set template values to provide options for edit selects
    global $TPL, $current_user, $isMessage;
    $db = new db_alloc;
    $projectID = $_GET["projectID"] or $projectID = $this->get_value("projectID");
    $TPL["personOptions"] = "<select name=\"personID\"><option value=\"\">".task::get_personList_dropdown($projectID)."</select>";
    $TPL["managerPersonOptions"] = task::get_managerPersonList_dropdown($projectID);

    // TaskType Options
    $taskType = new meta("taskType");
    $taskType_array = $taskType->get_assoc_array("taskTypeID","taskTypeID");
    $TPL["taskTypeOptions"] = page::select_options($taskType_array,$this->get_value("taskTypeID"));

    // Project dropdown
    $TPL["projectOptions"] = task::get_project_options($projectID);
    
    $commentTemplate = new commentTemplate();
    $ops = $commentTemplate->get_assoc_array("commentTemplateID","commentTemplateName","",array("commentTemplateType"=>"task"));
    $TPL["commentTemplateOptions"] = "<option value=\"\">Comment Templates</option>".page::select_options($ops);

    $priority = $this->get_value("priority") or $priority = 3;
    $taskPriorities = config::get_config_item("taskPriorities") or $taskPriorities = array();
    foreach ($taskPriorities as $k => $v) {
      $tp[$k] = $v["label"];
    }
    $TPL["priorityOptions"] = page::select_options($tp,$priority);
    $priority and $TPL["priorityLabel"] = " <div style=\"display:inline; color:".$taskPriorities[$priority]["colour"]."\">[".$this->get_priority_label()."]</div>";

    // We're building these two with the <select> tags because they will be
    // replaced by an AJAX created dropdown when the projectID changes.
    $TPL["parentTaskOptions"] = $this->get_parent_task_select();
    $TPL["interestedPartyOptions"] = $this->get_task_cc_list_select();

    $db->query(sprintf("SELECT fullName,emailAddress FROM interestedParty WHERE entity='task' AND entityID = %d ORDER BY fullName",$this->get_id()));
    while ($db->next_record()) {
      $str = trim(page::htmlentities($db->f("fullName")." <".$db->f("emailAddress").">"));
      $value = interestedParty::get_encoded_interested_party_identifier($db->f("fullName"), $db->f("emailAddress"));
      $TPL["interestedParty_hidden"].= $commar.$str."<input type=\"hidden\" name=\"interestedParty[]\" value=\"".$value."\">";
      $TPL["interestedParty_text"].= $commar.$str;
      $commar = "<br>";
    }

    $TPL["taskStatusLabel"] = $this->get_task_status("label");
    $TPL["taskStatusColour"] = $this->get_task_status("colour");
    $TPL["taskStatusValue"] = $this->get_task_status("value");
    $TPL["taskStatusOptions"] = page::select_options($this->get_task_statii_array(true),$this->get_value("taskStatus")."_".$this->get_value("taskSubStatus"));

    // If we're viewing the printer friendly view
    if ($_GET["media"] == "print") {
      // Parent Task label
      $t = new task;
      $t->set_id($this->get_value("parentTaskID"));
      $t->select();
      $TPL["parentTask"] = $t->get_display_value();

      // Task Type label
      $TPL["taskType"] = $this->get_value("taskTypeID"); 

      // Priority
      $TPL["priority"] = $this->get_value("priority");

      // Assignee label
      $p = new person;
      $p->set_id($this->get_value("personID"));
      $p->select();
      $TPL["person"] = $p->get_display_value();
  
      // Project label
      $p = new project;
      $p->set_id($this->get_value("projectID"));
      $p->select();
      $TPL["projectName"] = $p->get_display_value();
    }

  }

  function get_task_comments_array() {
    $rows = comment::util_get_comments_array("task",$this->get_id());
    $rows or $rows = array();
    return $rows;
  }

  function get_task_link($_FORM=array()) {
    $rtn = "<a href=\"".$this->get_url()."\">";
    $rtn.= $this->get_name($_FORM);
    $rtn.= "</a>";
    return $rtn;
  }

  function get_task_image() {
    global $TPL;
    return "<img class=\"taskType\" title=\"".$this->get_value("taskTypeID")."\" src=\"".$TPL["url_alloc_images"]."taskType_".$this->get_value("taskTypeID").".gif\">";
  }

  function get_name($_FORM=array()) {

    $_FORM["prefixTaskID"] and $id = $this->get_id()." ";

    if ($this->get_value("taskTypeID") == "Parent" && ($_FORM["return"] == "html" || $_FORM["return"] == "arrayAndHtml")) {
      $rtn = "<strong>".$id.$this->get_value("taskName",DST_HTML_DISPLAY)."</strong>";
    } else if ($_FORM["return"] == "html" || $_FORM["return"] == "arrayAndHtml") {
      $rtn = $id.$this->get_value("taskName",DST_HTML_DISPLAY);
    } else {
      $rtn = $id.$this->get_value("taskName");
    }
    return $rtn;
  }

  function get_url($absolute=false) {
    global $sess;
    $sess or $sess = new Session;

    $url = "task/task.php?taskID=".$this->get_id();

    if ($sess->Started() && !$absolute) {
      $url = $sess->url(SCRIPT_PATH.$url);

    // This for urls that are emailed
    } else {
      static $prefix;
      $prefix or $prefix = config::get_config_item("allocURL");
      $url = $prefix.$url;
    }
    return $url;
  }

  function get_task_statii_array($flat=false) {
    // This gets an array like that is useful for building the two types of
    // dropdown lists that taskStatus+taskSubStatus use
    $taskStatii = task::get_task_statii();

    if ($flat) {
      foreach ($taskStatii as $status => $sub) {
        foreach ($sub as $subStatus => $arr) {
          $taskStatiiArray[$status."_".$subStatus] = ucwords($status).": ".$arr["label"];
        }
      }
    } else {
      $taskStatiiArray[""] = ""; // blank entry
      foreach ($taskStatii as $status => $sub) {
        $taskStatiiArray[$status] = ucwords($status);
        foreach ($sub as $subStatus => $arr) {
          $taskStatiiArray[$status."_".$subStatus] = "&nbsp;&nbsp;&nbsp;&nbsp;".$arr["label"];
        }
      }
    } 

    return $taskStatiiArray;
  }

  function get_task_statii() {
    // looks like:
    //$arr["open"]["notstarted"] = array("label"=>"Not Started","colour"=>"#ffffff");
    //$arr["open"]["inprogress"] = array("label"=>"In Progress","colour"=>"#ffffff");
    //etc
    $rtn = config::get_config_item("taskStatusOptions") or $rtn = array();
    return $rtn;
  }

  function get_task_status($thing="") {
    $taskStatus = $this->get_value("taskStatus");
    $taskSubStatus = $this->get_value("taskSubStatus");
    return task::get_task_status_thing($thing,$taskStatus,$taskSubStatus);
  }

  function get_task_status_thing($thing="",$taskStatus="",$taskSubStatus="") {

    $arr = config::get_config_item("taskStatusOptions");
    if (!$taskSubStatus && $arr[$taskStatus]) {
      return ucwords($taskStatus);

    } else if ($thing && !$taskStatus && $taskSubStatus) {
      foreach ($arr as $k => $arr2) {
        foreach ($arr2 as $k2 => $arr3) {
          if ($k2 == $taskSubStatus) {
            if ($thing == "status") {
              return $k;
            } else if ($arr3[$thing]) {
              return $arr3[$thing];
            }
          }
        }
      } 

    } else if ($thing == "label" && $arr[$taskStatus][$taskSubStatus]["label"]) {
      return ucwords($taskStatus).": ".$arr[$taskStatus][$taskSubStatus]["label"];

    } else if ($thing == "value" && $arr[$taskStatus][$taskSubStatus]) {
      return $taskStatus."_".$taskSubStatus;

    } else if ($thing && $arr[$taskStatus][$taskSubStatus][$thing]) {
      return $arr[$taskStatus][$taskSubStatus][$thing];
    }
  }

  function get_list_filter($filter=array()) {

    if (!$filter["projectID"] && $filter["projectType"] && $filter["projectType"] != "all") {
      $db = new db_alloc;
      $q = project::get_project_type_query($filter["projectType"],$filter["current_user"],"current");
      $db->query($q);
      while ($db->next_record()) {
        $filter["projectIDs"][] = $db->f("projectID");
      }

      // Oi! What a pickle. Need this flag for when someone doesn't have entries loaded in the above while loop.
      $firstOption = true;

    // If projectID is an array
    } else if ($filter["projectID"] && is_array($filter["projectID"])) {
      $filter["projectIDs"] = $filter["projectID"];

    // Else a project has been specified in the url
    } else if ($filter["projectID"] && is_numeric($filter["projectID"])) {
      $filter["projectIDs"][] = $filter["projectID"];
    }


    // If passed array projectIDs then join them up with commars and put them in an sql subset
    if (is_array($filter["projectIDs"]) && count($filter["projectIDs"])) {
      $sql["projectIDs"] = "(project.projectID IN (".implode(",",$filter["projectIDs"])."))";

    // If there are no projects in $filter["projectIDs"][] and we're attempting the first option..
    } else if ($firstOption) {
      $sql["projectIDs"] = "(project.projectID IN (0))";
    }

    // taskDate filtering ...

    // New Tasks
    if ($filter["taskDate"] == "new") {
      $past = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 2, date("Y")))." 00:00:00";
      date("D") == "Mon" and $past = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 4, date("Y")))." 00:00:00";
      $sql[] = sprintf("(task.taskStatus != 'closed' AND task.dateCreated >= '".$past."')");

    // Due Today
    } else if ($filter["taskDate"] == "due_today") {
      $sql[] = "(task.taskStatus != 'closed' AND task.dateTargetCompletion = '".date("Y-m-d")."')";

    // Overdue
    } else if ($filter["taskDate"] == "overdue") {
      $sql[] = "(task.taskStatus != 'closed'
                AND 
                (task.dateTargetCompletion IS NOT NULL AND task.dateTargetCompletion != '' AND '".date("Y-m-d")."' > task.dateTargetCompletion))";
  
    // Date Created
    } else if ($filter["taskDate"] == "d_created") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateCreated >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateCreated <= '%s 23:59:59')",db_esc($filter["dateTwo"]));

    // Date Assigned
    } else if ($filter["taskDate"] == "d_assigned") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateAssigned >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateAssigned <= '%s 23:59:59')",db_esc($filter["dateTwo"]));

    // Date Target Start
    } else if ($filter["taskDate"] == "d_targetStart") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateTargetStart >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateTargetStart <= '%s')",db_esc($filter["dateTwo"]));

    // Date Target Completion
    } else if ($filter["taskDate"] == "d_targetCompletion") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateTargetCompletion >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateTargetCompletion <= '%s')",db_esc($filter["dateTwo"]));

    // Date Actual Start
    } else if ($filter["taskDate"] == "d_actualStart") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateActualStart >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateActualStart <= '%s')",db_esc($filter["dateTwo"]));

    // Date Actual Completion
    } else if ($filter["taskDate"] == "d_actualCompletion") {
      $filter["dateOne"] and $sql[] = sprintf("(task.dateActualCompletion >= '%s')",db_esc($filter["dateOne"]));
      $filter["dateTwo"] and $sql[] = sprintf("(task.dateActualCompletion <= '%s')",db_esc($filter["dateTwo"]));
    }

    // Task status filtering
    if (is_array($filter["taskStatus"])) {
      $subsql = array();
      foreach ($filter["taskStatus"] as $status) {
        list($taskStatus,$taskSubStatus) = explode("_",$status);
        if($taskStatus) {
          if($taskSubStatus) {
            $subsql[] = sprintf("(taskStatus = '%s' AND taskSubStatus = '%s')",db_esc($taskStatus),db_esc($taskSubStatus));
          } else {
            $subsql[] = sprintf("(taskStatus = '%s')",db_esc($taskStatus));
          }
        }
      }
      $sql[] = '(' . implode(" OR ", $subsql) . ')';
    } elseif ($filter["taskStatus"]) {
      list($taskStatus,$taskSubStatus) = explode("_",$filter["taskStatus"]);
      $taskStatus    and $sql[] = sprintf("(taskStatus = '%s')",db_esc($taskStatus));
      $taskSubStatus and $sql[] = sprintf("(taskSubStatus = '%s')",db_esc($taskSubStatus));
    }

    // Unset if they've only selected the topmost empty task type
    if (is_array($filter["taskTypeID"]) && count($filter["taskTypeID"])>=1 && !$filter["taskTypeID"][0]) {
      unset($filter["taskTypeID"][0]);
    }

    // If many create an SQL taskTypeID in (set) 
    if (is_array($filter["taskTypeID"]) && count($filter["taskTypeID"])) {
      $sql[] = "(taskTypeID in ('".implode("','",$filter["taskTypeID"])."'))";
    
    // Else if only one taskTypeID
    } else if ($filter["taskTypeID"]) {
      $sql[] = sprintf("(taskTypeID = '%s')",$filter["taskTypeID"]);
    }

    // Filter on taskID
    if ($filter["taskID"]) {     
      $sql[] = sprintf("(taskID = %d)", db_esc($filter["taskID"]));
    }
    // Filter on %taskName%
    if ($filter["taskName"]) {     
      $sql[] = sprintf("(taskName LIKE '%%%s%%')", db_esc($filter["taskName"]));
    }
    // If personID filter
    if ($filter["personID"]) {
      $sql["personID"] = sprintf("(personID = %d)",$filter["personID"]);
    }
    // If creatorID filter
    if ($filter["creatorID"]) {
      $sql["creatorID"] = sprintf("(creatorID = %d)",$filter["creatorID"]);
    }
    // If managerID filter
    if ($filter["managerID"]) {
      $sql["managerID"] = sprintf("(managerID = %d)",$filter["managerID"]);
    }

    // These filters are for the time sheet dropdown list
    if ($filter["taskTimeSheetStatus"] == "open") {
      unset($sql["personID"]);
      $sql[] = sprintf("(task.taskStatus != 'closed')");

    } else if ($filter["taskTimeSheetStatus"] == "not_assigned"){ 
      unset($sql["personID"]);
      $sql[] = sprintf("((task.taskStatus != 'closed') AND personID != %d)",$filter["personID"]);

    } else if ($filter["taskTimeSheetStatus"] == "recent_closed"){
      unset($sql["personID"]);
      $sql[] = sprintf("(task.dateActualCompletion >= DATE_SUB(CURDATE(),INTERVAL 14 DAY))");

    } else if ($filter["taskTimeSheetStatus"] == "all") {
    }

    $filter["parentTaskID"] and $sql["parentTaskID"] = sprintf("(parentTaskID = %d)",$filter["parentTaskID"]);
    return $sql;
  }

  function get_recursive_child_tasks($taskID_of_parent, $rows=array(), $padding=0) {
    $rtn = array();
    $rows or $rows = array();
    foreach($rows as $taskID => $v) {
      $parentTaskID = $v["parentTaskID"];
      $row = $v["row"];

      if ($taskID_of_parent == $parentTaskID) {
        $row["padding"] = $padding;
        $rtn[$taskID]["row"] = $row;
        unset($rows[$taskID]);
        $padding+=1;
        $children = task::get_recursive_child_tasks($taskID,$rows,$padding);
        $padding-=1;

        if (count($children)) {
          $rtn[$taskID]["children"] = $children;
        }
      }
    }
    return $rtn;
  }

  function build_recursive_task_list($t=array(),$_FORM=array()) {
    $tasks or $tasks = array();
    $summary_ops or $summary_ops = array();
    foreach ($t as $r) {
      $row = $r["row"];
      $done[$row["taskID"]] = true; // To track orphans

      list($t,$s,$o) = task::load_task_list_row_details($row,$_FORM);
      $t and $tasks += $t;
      $s and $summary.= $s;
      $o and $summary_ops += $o;

      if ($r["children"]) {
        list($t,$s,$o,$d) = task::build_recursive_task_list($r["children"],$_FORM);
        $t and $tasks += $t;
        $s and $summary.= $s;
        $o and $summary_ops += $o;
        $d and $done += $d;
      }
    }
    return array($tasks,$summary,$summary_ops,$done);
  }

  function load_task_list_row_details($row,$_FORM=array()) {
    $summary_ops[$row["taskID"]] = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;",$row["padding"]).$row["taskID"]." ".$row["taskName"];
    $tasks[$row["taskID"]] = $row;
    $summary.= task::get_list_tr($row,$_FORM);
    return array($tasks,$summary,$summary_ops);
  }

  function get_list($_FORM) {

    /*
     * This is the definitive method of getting a list of tasks that need a sophisticated level of filtering
     *
     */
 
    $filter = task::get_list_filter($_FORM);

    $debug = $_FORM["debug"];
    $debug and print "\n<pre>_FORM: ".print_r($_FORM,1)."</pre>";
    $debug and print "\n<pre>filter: ".print_r($filter,1)."</pre>";

    // Zero is a valid limit
    if ($_FORM["limit"] || $_FORM["limit"] === 0 || $_FORM["limit"] === "0") {
      $limit = sprintf("limit %d",$_FORM["limit"]); 
    }
    $_FORM["return"] or $_FORM["return"] = "html";

    if ($_FORM["showDates"]) {
      $_FORM["showDate1"] = true;
      $_FORM["showDate2"] = true;
      $_FORM["showDate3"] = true;
      $_FORM["showDate4"] = true;
      $_FORM["showDate5"] = true;
    }

    $_FORM["people_cache"] = get_cached_table("person");
    $_FORM["timeUnit_cache"] = get_cached_table("timeUnit");
    $_FORM["taskPriorities"] = config::get_config_item("taskPriorities");
    $_FORM["projectPriorities"] = config::get_config_item("projectPriorities");

    // Get a hierarchical list of tasks
    if ($_FORM["taskView"] == "byProject") {
      if (is_array($filter) && count($filter)) {
        $f = " WHERE ".implode(" AND ",$filter);
      }
      $db = new db_alloc;
      $q = sprintf("SELECT task.*, projectName, projectPriority
                      FROM task
                 LEFT JOIN project ON project.projectID = task.projectID
                           %s
                  GROUP BY task.taskID
                  ORDER BY projectName,taskName
                   ",$f);
      
      $_FORM["debug"] and print "\n<br>QUERY: ".$q;
      $db->query($q);
      while ($row = $db->next_record()) {
        $task = new task;
        $task->read_db_record($db);
        $row["taskURL"] = $task->get_url();
        $row["project_name"] = $db->f("projectName");
        $row["projectPriority"] = $db->f("projectPriority");
        $row["taskName"] = $task->get_name($_FORM);
        $row["taskLink"] = $task->get_task_link($_FORM);
        $row["taskTypeImage"] = $task->get_task_image();
        $row["taskStatusLabel"] = $task->get_task_status("label");
        $row["taskStatusColour"] = $task->get_task_status("colour");
        $row["newSubTask"] = $task->get_new_subtask_link();
        $_FORM["showDateStatus"] and $row["taskDateStatus"] = $task->get_dateStatus();
        $_FORM["showTimes"] and $row["percentComplete"] = $task->get_percentComplete();
        $_FORM["showPriority"] and $row["priorityFactor"] = task::get_overall_priority($row["projectPriority"], $row["priority"] ,$row["dateTargetCompletion"]);
        if (!$_FORM["skipObject"]) {
          $_FORM["return"] == "arrayAndHtml" || $_FORM["return"] == "array" and $row["object"] = $task;
        }
        $row["padding"] = $_FORM["padding"];
        $row["taskID"] = $task->get_id();
        $row["parentTaskID"] = $task->get_value("parentTaskID");
        $rows[$task->get_id()] = array("parentTaskID"=>$row["parentTaskID"],"row"=>$row);
      }
    
      $rows or $rows = array();
      $tasks or $tasks = array();
      $summary_ops or $summary_ops = array();
  
      $parentTaskID = $_FORM["parentTaskID"] or $parentTaskID = 0;
      $t = task::get_recursive_child_tasks($parentTaskID,$rows);
      list($tasks,$summary,$summary_ops,$done) = task::build_recursive_task_list($t,$_FORM);

      // This bit appends the orphan tasks onto the end..
      foreach ($rows as $taskID => $r) {
        $row = $r["row"];
        $row["padding"] = 0;
        if (!$done[$taskID]) {
          list($t,$s,$o) = task::load_task_list_row_details($row,$_FORM);
          $t and $tasks += $t;
          $s and $summary.= $s;
          $o and $summary_ops += $o;
        }
      }
  
      if ((is_array($tasks) && count($tasks)) || $s || (is_array($summary_ops) && count($summary_ops))) {
        $print = true;
      }


    // Else get a prioritised list of tasks..
    } else if ($_FORM["taskView"] == "prioritised") {
          
      unset($filter["parentTaskID"]);
      if (is_array($filter) && count($filter)) {
        $filter = " WHERE ".implode(" AND ",$filter);
      }

      $q = "SELECT task.*, projectName, projectShortName, clientID, projectPriority, 
                   priority * POWER(projectPriority, 2) * 
                       IF(task.dateTargetCompletion IS NULL, 
                         8,
                         ATAN(
                              (TO_DAYS(task.dateTargetCompletion) - TO_DAYS(NOW())) / 20
                             ) / 3.14 * 8 + 4
                         ) / 10 as priorityFactor
              FROM task LEFT JOIN project ON task.projectID = project.projectID 
             ".$filter." ORDER BY priorityFactor ".$limit;
      $debug and print "\n<br>QUERY: ".$q;
      $db = new db_alloc;
      $db->query($q);
      while ($row = $db->next_record()) {
        $print = true;
        $row["project_name"] = $row["projectShortName"]  or  $row["project_name"] = $row["projectName"];
        $t = new task;
        $t->read_db_record($db);
        $row["taskURL"] = $t->get_url();
        $row["taskName"] = $t->get_name($_FORM);
        $row["taskLink"] = $t->get_task_link($_FORM);
        $row["taskTypeImage"] = $t->get_task_image();
        $row["newSubTask"] = $t->get_new_subtask_link();
        $row["taskStatusLabel"] = $t->get_task_status("label");
        $row["taskStatusColour"] = $t->get_task_status("colour");
        $_FORM["showDateStatus"] and $row["taskDateStatus"] = $t->get_dateStatus($_FORM["return"]);
        if (!$_FORM["skipObject"]) {
          $row["object"] = $t;
        }
        $_FORM["showTimes"] and $row["percentComplete"] = $t->get_percentComplete();
        $_FORM["showPriority"] and $row["priorityFactor"] = task::get_overall_priority($row["projectPriority"], $row["priority"], $row["dateTargetCompletion"]);
        $tasks[$row["taskID"]] = $row;
      }
    } 


    if ($_FORM["taskView"] == "prioritised") {

      if (is_array($tasks) && count($tasks)) {
        uasort($tasks, array("task", "priority_compare"));
      } else {
        $tasks = array();
      }
        
      if ($_FORM["return"] == "text"){
        foreach ($tasks as $row) {
          $summary.= task::get_list_tr_text($row,$_FORM);
        }

      } else {
        foreach ($tasks as $row) {
          $summary.= task::get_list_tr($row,$_FORM);
        }
      }
    }

    $header = task::get_list_header($_FORM);
    $footer = task::get_list_footer($_FORM);

    // Decide what to actually return
    if ($print && $_FORM["return"] == "arrayAndHtml") { // sheesh
      return array($tasks,$header.$summary.$footer);

    } else if (!$print && $_FORM["return"] == "arrayAndHtml") { 
      $rtn = "<table style=\"width:100%\"><tr><td style=\"text-align:center\"><b>No Tasks Found</b></td></tr></table>";
      return array(array(),$rtn);
      
    } else if ($print && $_FORM["return"] == "array") {
      return $tasks;

    } else if ($print && $_FORM["return"] == "html") {
      return $header.$summary.$footer;

    } else if ($print && $_FORM["return"] == "text") {
      return $summary;

    } else if ($print && $_FORM["return"] == "dropdown_options") {
      return $summary_ops;

    } else if (!$print && ($_FORM["return"] == "html" || $_FORM["return"] == "arrayAndHtml")) {
      return "<table style=\"width:100%\"><tr><td style=\"text-align:center\"><b>No Tasks Found</b></td></tr></table>";
    } 
  } 

  function priority_compare($a, $b) {
    return $a["priorityFactor"] > $b["priorityFactor"];
  }

  function get_overall_priority($projectPriority=0,$taskPriority=0,$dateTargetCompletion) {
    if ($dateTargetCompletion) {
      $daysUntilDue = (format_date("U",$dateTargetCompletion) - mktime()) / 60 / 60 / 24;
      $mult = atan($daysUntilDue / 20) / 3.14 * 8 + 4;
    } else {
      $mult = 8;
    }

    $priorityFactor = ($taskPriority * pow($projectPriority,2)) * $mult / 10;
    return $priorityFactor;
  }

  function get_list_header($_FORM) {
    global $TPL;
    if ($_FORM["showHeader"]) {
      if($_FORM["showEdit"]) {
        $summary[] = "<form action=\"".$_FORM["url_form_action"]."\" method=\"post\">";
      }
      #$_FORM["taskView"] == "byProject" and $summary[] = "<br>".$_FORM["projectLinks"];
      $summary[] = "<table class=\"list sortable\">";
      $summary[] = "<tr>";
      $_FORM["showEdit"]     and $summary[] = "<th width=\"1%\" class=\"sorttable_nosort noprint\"><input type=\"checkbox\" onclick=\"return $('.task_checkboxes').each(function(){this.checked=!this.checked});\"></th>";
                                 $summary[] = "<th width=\"1%\"></th>"; //taskTypeImage
      $_FORM["showTaskID"]   and $summary[] = "<th class=\"sorttable_numeric\" width=\"1%\">ID</th>";
                                 $summary[] = "<th>Task</th>";
      $_FORM["showProject"]  and $summary[] = "<th>Project</th>";
      $_FORM["showPriority"] and $summary[] = "<th class=\"sorttable_numeric\">Priority</th>";
      $_FORM["showPriority"] and $summary[] = "<th>Task Pri</th>";
      $_FORM["showPriority"] and $summary[] = "<th>Proj Pri</th>";
      $_FORM["showDateStatus"] and $summary[] = "<th>Date Status</th>";
      $_FORM["showCreator"]  and $summary[] = "<th>Task Creator</th>";
      $_FORM["showManager"]  and $summary[] = "<th>Task Manager</th>";
      $_FORM["showAssigned"] and $summary[] = "<th>Assigned To</th>";
      $_FORM["showDate1"]    and $summary[] = "<th>Targ Start</th>";
      $_FORM["showDate2"]    and $summary[] = "<th>Targ Compl</th>";
      $_FORM["showDate3"]    and $summary[] = "<th>Act Start</th>";
      $_FORM["showDate4"]    and $summary[] = "<th>Act Compl</th>";
      $_FORM["showDate5"]    and $summary[] = "<th>Task Created</th>";
      $_FORM["showTimes"]    and $summary[] = "<th>Estimate</th>";
      $_FORM["showTimes"]    and $summary[] = "<th>Actual</th>";
      $_FORM["showTimes"]    and $summary[] = "<th>%</th>";
      $_FORM["showStatus"]   and $summary[] = "<th>Status</th>";
      $summary[] ="</tr>";

      return implode("\n",$summary);
    }
  }

  function get_task_priority_dropdown($priority=false) {
    $taskPriorities = config::get_config_item("taskPriorities") or $taskPriorities = array();
    foreach ($taskPriorities as $k => $v) {
      $tp[$k] = $v["label"];     
    }
    return page::select_options($tp,$priority);
  }

  function get_list_footer($_FORM) {
    global $TPL;
    if($_FORM["showEdit"]) {
      $person_options = page::select_options(person::get_username_list());
      $assignee_dropdown = "<select name=\"personID\"><option value=\"\">".$person_options."</select>";
      $manager_dropdown = "<select name=\"managerID\"><option value=\"\">".$person_options."</select>";
      $dateTargetStart = page::calendar("dateTargetStart");
      $dateTargetCompletion = page::calendar("dateTargetCompletion");
      $dateActualStart = page::calendar("dateActualStart");
      $dateActualCompletion = page::calendar("dateActualCompletion");
      $priority_options = task::get_task_priority_dropdown(3);
      $taskStatus_options = page::select_options(task::get_task_statii_array(true));
      $taskType = new meta("taskType");
      $taskType_array = $taskType->get_assoc_array("taskTypeID","taskTypeID");
      $taskType_options = page::select_options($taskType_array);
      $js = "makeAjaxRequest('".$TPL["url_alloc_updateParentTasks"]."projectID='+$(this).val(), 'parentTaskDropdown')";
      $project_dropdown = "<select name=\"projectID\" id=\"projectID\" onChange=\"".$js."\"><option value=\"\">".task::get_project_options()."</select>";
      $parentTask_div = "<div style=\"display:inline\" id=\"parentTaskDropdown\"></div>";
      $arr = "--&gt;";

      $ret[] = "<tfoot>
                  <tr>
                    <th colspan=\"25\" class=\"nobr noprint\" style=\"padding:2px;\">
                      <div style=\"float:left\">
                        <select name=\"update_action\" onChange=\"$('.hidden').hide(); $('#'+$(this).val()+'_div').css('display','inline');\"> 
                          <option value=\"\">Modify Checked...</options>
                          <option value=\"personID\">Assign to ".$arr."</options>
                          <option value=\"managerID\">Manager to ".$arr."</options>
                          <option value=\"timeEstimate\">Estimate to ".$arr."</options>
                          <option value=\"priority\">Task Priority to ".$arr."</options>
                          <option value=\"taskTypeID\">Task Type to ".$arr."</options>
                          <option value=\"dateTargetStart\">Target Start Date to ".$arr."</options>
                          <option value=\"dateTargetCompletion\">Target Completion Date to ".$arr."</options>
                          <option value=\"dateActualStart\">Actual Start Date to ".$arr."</options>
                          <option value=\"dateActualCompletion\">Actual Completion Date to ".$arr."</options>
                          <option value=\"projectIDAndParentTaskID\">Project and Parent Task to ".$arr."</options>
                          <option value=\"taskStatus\">Task Status to ".$arr."</option>
                        </select>
                      </div>
                      <div class=\"hidden\" id=\"dateTargetStart_div\">".$dateTargetStart."</div>
                      <div class=\"hidden\" id=\"dateTargetCompletion_div\">".$dateTargetCompletion."</div>
                      <div class=\"hidden\" id=\"dateActualStart_div\">".$dateActualStart."</div>
                      <div class=\"hidden\" id=\"dateActualCompletion_div\">".$dateActualCompletion."</div>
                      <div class=\"hidden\" id=\"personID_div\">".$assignee_dropdown."</div>
                      <div class=\"hidden\" id=\"managerID_div\">".$manager_dropdown."</div>
                      <div class=\"hidden\" id=\"timeEstimate_div\"><input name=\"timeEstimate\" type=\"text\" size=\"5\"></div>
                      <div class=\"hidden\" id=\"priority_div\"><select name=\"priority\">".$priority_options."</select></div>
                      <div class=\"hidden\" id=\"taskTypeID_div\"><select name=\"taskTypeID\">".$taskType_options."</select></div>
                      <div class=\"hidden\" id=\"projectIDAndParentTaskID_div\">".$project_dropdown.$parentTask_div."</div>
                      <div class=\"hidden\" id=\"taskStatus_div\"><select name=\"taskStatus\">".$taskStatus_options."</select></div>
                      <input type=\"submit\" name=\"run_mass_update\" value=\"Update Tasks\">
                    </th>
                  </tr>
                </tfoot>";
    }

    $ret[] = "</table>";

    if($_FORM["showEdit"]) {
      $ret[] = "</form>";
    }

    return implode("\n",$ret);
  }

  function get_list_tr_text($task,$_FORM) {
    $summary[] = "";
    $summary[] = "";
    $summary[] = "Project: ".$task["project_name"];
    $summary[] = "Task: ".$task["taskName"];
    $summary[] = $task["taskStatusLabel"];
    $summary[] = $task["taskURL"];
    return implode("\n",$summary);
  }

  function get_list_tr($task,$_FORM) {
    global $TPL;

    if ($_FORM["showDescription"] || $_FORM["showComments"]) {
      if ($task["taskDescription"]) {
        $str[] = page::htmlentities($task["taskDescription"]);
      }
      if ($_FORM["showComments"]) {
        $comments = comment::util_get_comments("task",$task["taskID"]);
        if ($comments) {
          $str[] = $comments;
        }
      }
      if (is_array($str) && count($str)) {
        $str = "<br>".implode("<br>",$str);
      }
    }

    $task["timeEstimate"] !== NULL and $task["timeEstimate"] = $task["timeEstimate"]*60*60;
    $task["_FORM"] = $_FORM;
    $task["str"] = $str;
    $TPL = array_merge($TPL,(array)$task);
    return include_template(dirname(__FILE__)."/../templates/taskListR.tpl", true);
  }  

  function get_new_subtask_link() {
    global $TPL;
    if (is_object($this) && $this->get_value("taskTypeID") == "Parent") {
      return "<a class=\"noprint\" href=\"".$TPL["url_alloc_task"]."projectID=".$this->get_value("projectID")."&parentTaskID=".$this->get_id()."\">New Subtask</a>";
    }
  }

  function get_time_billed($taskID="", $recurse=false) {
    static $results;
    if (is_object($this) && !$taskID) {
      $taskID = $this->get_id();
    }
    if ($results[$taskID]) {
      return $results[$taskID];
    }
    if ($taskID) {
      $db = new db_alloc;
      // Get tally from timeSheetItem table
      $db->query("SELECT sum(timeSheetItemDuration*timeUnitSeconds) as sum_of_time
                    FROM timeSheetItem 
               LEFT JOIN timeUnit ON timeSheetItemDurationUnitID = timeUnitID 
                   WHERE taskID = %d
               GROUP BY taskID",$taskID);
      while ($db->next_record()) {
        $results[$taskID] = $db->f("sum_of_time");
        return $db->f("sum_of_time");
      }
      return "";
    }
  }

  function get_percentComplete($get_num=false) {

    $timeActual = sprintf("%0.2f",$this->get_time_billed());
    $timeEstimate = sprintf("%0.2f",$this->get_value("timeEstimate")*60*60);

    if ($timeEstimate>0 && is_object($this)) {

      $percent = $timeActual / $timeEstimate * 100;
      $this->get_value("dateActualCompletion") and $closed_text = "<del>" and $closed_text_end = "</del> Closed";
 
      // Return number
      if ($get_num) {
        $this->get_value("dateActualCompletion") || $percent>100 and $percent = 100;
        return $percent;
       
      // Else if task <= 100%
      } else if ($percent <= 100) {
        return $closed_text.sprintf("%d%%",$percent).$closed_text_end;
                    
       
      // Else if task > 100%
      } else if ($percent > 100) {
        return "<span class='bad'>".$closed_text.sprintf("%d%%",$percent).$closed_text_end."</span>";
      }
    }
  }

  function get_priority_label() {
    $taskPriorities = config::get_config_item("taskPriorities");
    return $taskPriorities[$this->get_value("priority")]["label"];
  }

  function get_forecast_completion() {
    // Get the date the task is forecast to be completed given an actual start 
    // date and percent complete
    $date_actual_start = $this->get_value("dateActualStart");
    $percent_complete = $this->get_percentComplete(true);

    if (!($date_actual_start && $percent_complete)) {
      // Can't calculate forecast date without date_actual_start and % complete
      return 0;
    }

    $date_actual_start = format_date("U",$date_actual_start);
    $time_spent = mktime() - $date_actual_start;
    $time_per_percent = $time_spent / $percent_complete;
    $percent_left = 100 - $percent_complete;
    $time_left = $percent_left * $time_per_percent;
    $date_forecast_completion = mktime() + $time_left;
    return $date_forecast_completion;
  }

  function get_dateStatus($format = "html", $type = "standard") {
    $today = date("Y-m-d");
    define("UNKNOWN", 0);
    define("NOT_STARTED", 1);
    define("STARTED", 2);
    define("COMPLETED", 3);

    $date_target_start = $this->get_value("dateTargetStart");
    $date_target_completion = $this->get_value("dateTargetCompletion");
    $date_actual_start = $this->get_value("dateActualStart");
    $date_actual_completion = $this->get_value("dateActualCompletion");

    // First figure out where we should be with this task
    if ($date_target_completion != "" && $date_target_completion <= $today) {
      $target = COMPLETED;
    } else if ($date_target_start != "" && $date_target_start <= $today) {
      $target = STARTED;
    } else if ($date_target_start) {
      $target = NOT_STARTED;
    } else {
      $target = UNKNOWN;
    }

    // Now figure out where we are
    if ($date_actual_completion) {
      $actual = COMPLETED;
    } else if ($date_actual_start) {
      $actual = STARTED;
    } else {
      $actual = NOT_STARTED;
    }

    // Now compare the target and the actual and provide the results
    if ($actual == COMPLETED) {
      if ($type != "brief") {
        $status = "Completed on ".$date_actual_completion;
      } else {
        $status = "Completed";
      }
    } else if ($actual == STARTED) {
      $date_forecast_completion = $this->get_forecast_completion();

      $status = "Started ".$date_actual_start.", ";

      #if ($type != "brief") {
      #  if ($percent_complete == "") {
      #    $status.= "% complete not set, ";
      #  } else {
      #    $status.= "$percent_complete% complete, ";
      #  }
      #}

      if ($date_target_completion != "") {
        $status.= "Target completion $date_target_completion ";
      } else {
    
      }

      if ($type != "brief") {
        if ($date_forecast_completion == 0) {
          $status.= "forecast completion date not available";
        } else {
          $status.= "forecast completion date of	".date("Y-m-d", $date_forecast_completion);
        }
      }

      if ($target == COMPLETED) {
        if ($type == "brief") {
          $status = "Overdue for completion on ".$date_target_completion;
        } else {
          $status = "Overdue for completion - $status";
        }
        if ($format == "html" || $format == "arrayAndHtml") {
          $status = "<strong class=\"overdue\">$status</strong>";
        }
      } else if ($date_target_completion && date("Y-m-d", $date_forecast_completion) > $date_target_completion) {
        $status = "Behind target - $status";
        if ($format == "html" || $format == "arrayAndHtml") {
          $status = "<strong class=\"behind-target\">$status</strong>";
        }
      }

    // New one
    } else if ($actual == NOT_STARTED && $target == UNKNOWN) {
      if ($target_completion_date) {
        $status = "Not started, due to be completed by $target_completion_date, no target start date";
      } else {
        $status = "Not started, no targets";
      }
    } else if ($actual == NOT_STARTED && $target == NOT_STARTED) {
      $status = "Due to start on ".$date_target_start;
      if ($date_target_completion) {
        $status.= " and to be completed by ".$date_target_completion;
      } else {
        $status.= ", no target completion date";
      }
    } else if ($actual == NOT_STARTED && $target == STARTED) {
      $status = "Overdue to start on ".$date_target_start;
      if ($format == "html" || $format == "arrayAndHtml") {
        $status = "<strong class=\"behind-target\">$status</strong>";
      }
    } else if ($actual == NOT_STARTED && $target == COMPLETED) {
      $status = "Overdue to start and be completed by $date_target_completion";
      if ($format == "html" || $format == "arrayAndHtml") {
        $status = "<strong class=\"overdue\">$status</strong>";
      }
    } else {
      $status = "Unexpected target/actual combination: $target/$actual";
    }

    // $status .= " ($target/$actual)";
    return $status;
  }

  function get_list_vars() {
    $taskStatii = task::get_task_statii_array();
    foreach($taskStatii as $k => $v) {
      $taskStatiiStr.= $pipe.$k;
      $pipe = " | ";
    }

    return array("taskView"             => "[MANDATORY] eg: byProject | prioritised"
                ,"return"               => "[MANDATORY] eg: html | text | array | dropdown_options | arrayAndHtml"
                ,"limit"                => "Appends an SQL limit (only for prioritised and objects views)"
                ,"projectIDs"           => "An array of projectIDs"
                ,"projectID"            => "A single projectID"
                ,"taskStatus"           => $taskStatiiStr
                ,"taskDate"             => "new | due_today | overdue | d_created | d_assigned | d_targetStart | d_targetCompletion | d_actualStart | d_actualCompletion (all the d_* options require a dateOne (From Date) or a dateTwo (To Date) to be filled)"
                ,"dateOne"              => "From Date (must be used with a d_* taskDate option)"
                ,"dateTwo"              => "To Date (must be used with a d_* taskDate option)"
                ,"taskTimeSheetStatus"  => "my_open | not_assigned | my_closed | my_recently_closed | all"
                ,"taskTypeID"           => "Task | Parent | Message | Fault | Milestone"
                ,"current_user"         => "Lets us fake a current_user id for when generating task emails and there is no \$current_user object"
                ,"taskID"               => "Task ID"
                ,"taskName"             => "Task Name (eg: *install*)"
                ,"creatorID"            => "Task creator"
                ,"managerID"            => "The person managing task"
                ,"personID"             => "The person assigned to the task"
                ,"parentTaskID"         => "ID of parent task, all top level tasks have parentTaskID of 0, so this defaults to 0"
                ,"projectType"          => "mine | pm | tsm | pmORtsm | curr | pote | arch | all"
                ,"applyFilter"          => "Saves this filter as the persons preference"
                ,"padding"              => "Initial indentation level (useful for byProject lists)"
                ,"url_form_action"      => "The submit action for the filter form"
                ,"form_name"            => "The name of this form, i.e. a handle for referring to this saved form"
                ,"dontSave"             => "Specify that the filter preferences should not be saved this time"
                ,"skipObject"           => "Services coming over SOAP should set this true to minimize the amount of bandwidth"
                ,"showDates"            => "Show dates 1-4"
                ,"showDate1"            => "Date Target Start"
                ,"showDate2"            => "Date Target Completion"
                ,"showDate3"            => "Date Actual Start"
                ,"showDate4"            => "Date Actual Completion"
                ,"showDate5"            => "Date Created"
                ,"showProject"          => "The tasks Project (has different layout when prioritised vs byProject)"
                ,"showPriority"         => "The calculated overall priority, then the tasks, then the projects priority"
                ,"showStatus"           => "A colour coded textual description of the status of the task"
                ,"showDateStatus"       => "A colour coded textual description of the *dates* status of the task"
                ,"showCreator"          => "The tasks creator"
                ,"showAssigned"         => "The person assigned to the task"
                ,"showTimes"            => "The original estimate and the time billed and percentage"
                ,"showHeader"           => "A descriptive html header row"
                ,"showDescription"      => "The tasks description"
                ,"showComments"         => "The tasks comments"
                ,"showTaskID"           => "The task ID"
                ,"showManager"          => "Show the tasks manager"
                ,"showPercent"          => "The percent complete"
                ,"showEdit"             => "Display the html edit controls to allow en masse task editing"
                );
  }

  function load_form_data($defaults=array()) {
    global $current_user;
  
    $page_vars = array_keys(task::get_list_vars());

    $_FORM = get_all_form_data($page_vars,$defaults);

    if ($_FORM["projectID"] && !is_array($_FORM["projectID"])) {
      $p = $_FORM["projectID"];
      unset($_FORM["projectID"]);
      $_FORM["projectID"][] = $p;

    } else if (!$_FORM["projectType"]){
      $_FORM["projectType"] = "mine";
    }

    if ($_FORM["applyFilter"] && is_object($current_user)) {
      // we have a new filter configuration from the user, and must save it
      if(!$_FORM["dontSave"]) {
        $url = $_FORM["url_form_action"];
        unset($_FORM["url_form_action"]);
        $current_user->prefs[$_FORM["form_name"]] = $_FORM;
        $_FORM["url_form_action"] = $url;
      }
    } else {
      // we haven't been given a filter configuration, so load it from user preferences
      $_FORM = $current_user->prefs[$_FORM["form_name"]];
      if (!isset($current_user->prefs[$_FORM["form_name"]])) {
        $_FORM["projectType"] = "mine";
        $_FORM["taskStatus"] = "open";
        $_FORM["personID"] = $current_user->get_id();
      }
    }

    // If have check Show Description checkbox then display the Long Description and the Comments
    if ($_FORM["showDescription"]) {
      $_FORM["showComments"] = true;
    } else {
      unset($_FORM["showComments"]);
    }
    $_FORM["taskView"] or $_FORM["taskView"] = "byProject";
    return $_FORM;
  }

  function load_task_filter($_FORM) {
    global $current_user;

    $db = new db_alloc;

    // Load up the forms action url
    $rtn["url_form_action"] = $_FORM["url_form_action"];

    // Load up the filter bits
    $rtn["projectOptions"] = project::get_list_dropdown($_FORM["projectType"],$_FORM["projectID"]);

    $_FORM["projectType"] and $rtn["projectType_checked_".$_FORM["projectType"]] = " checked"; 

    $rtn["personOptions"] = "\n<option value=\"\"> ";
    $rtn["personOptions"].= page::select_options(person::get_username_list($_FORM["personID"]), $_FORM["personID"]);

    $rtn["creatorPersonOptions"] = "\n<option value=\"\"> ";
    $rtn["creatorPersonOptions"].= page::select_options(person::get_username_list($_FORM["creatorID"]), $_FORM["creatorID"]);

    $rtn["managerPersonOptions"] = "\n<option value=\"\"> ";
    $rtn["managerPersonOptions"].= page::select_options(person::get_username_list($_FORM["managerID"]), $_FORM["managerID"]);

    $taskType = new meta("taskType");
    $taskType_array = $taskType->get_assoc_array("taskTypeID","taskTypeID");
    $rtn["taskTypeOptions"] = page::select_options($taskType_array,$_FORM["taskTypeID"]);

    $_FORM["taskView"] and $rtn["taskView_checked_".$_FORM["taskView"]] = " checked";

    $taskStatii = task::get_task_statii_array();
    $rtn["taskStatusOptions"] = page::select_options($taskStatii, $_FORM["taskStatus"]);

    $_FORM["showDescription"] and $rtn["showDescription_checked"] = " checked";
    $_FORM["showDates"]       and $rtn["showDates_checked"]       = " checked";
    $_FORM["showCreator"]     and $rtn["showCreator_checked"]     = " checked";
    $_FORM["showAssigned"]    and $rtn["showAssigned_checked"]    = " checked";
    $_FORM["showTimes"]       and $rtn["showTimes_checked"]       = " checked";
    $_FORM["showPercent"]     and $rtn["showPercent_checked"]     = " checked";
    $_FORM["showPriority"]    and $rtn["showPriority_checked"]    = " checked";
    $_FORM["showDateStatus"]  and $rtn["showDateStatus_checked"]  = " checked";
    $_FORM["showTaskID"]      and $rtn["showTaskID_checked"]      = " checked";
    $_FORM["showManager"]     and $rtn["showManager_checked"]     = " checked";
    
    $arrow = " --&gt;";
    $taskDateOps = array(""                   => ""
                        ,"new"                => "New Tasks"
                        ,"due_today"          => "Due Today"
                        ,"overdue"            => "Overdue"
                        ,"d_created"          => "Date Created".$arrow
                        ,"d_assigned"         => "Date Assigned".$arrow
                        ,"d_targetStart"      => "Estimated Start".$arrow
                        ,"d_targetCompletion" => "Estimated Completion".$arrow
                        ,"d_actualStart"      => "Date Started".$arrow
                        ,"d_actualCompletion" => "Date Completed".$arrow
                        );
    $rtn["taskDateOptions"] = page::select_options($taskDateOps, $_FORM["taskDate"]);

    if (!in_array($_FORM["taskDate"],array("new","due_today","overdue"))) {
      $rtn["dateOne"] = $_FORM["dateOne"];
      $rtn["dateTwo"] = $_FORM["dateTwo"];
    }


    // Get
    $rtn["FORM"] = "FORM=".urlencode(serialize($_FORM));

    return $rtn;
  }

  function send_emails($selected_option, $type="", $body="", $from=array()) {
    global $current_user;

    $recipients = comment::get_email_recipients($selected_option,$from);
    list($to_address,$bcc,$successful_recipients) = comment::get_email_recipient_headers($recipients, $from);

    if ($successful_recipients) {
      $email = new alloc_email();
      $bcc && $email->add_header("Bcc",$bcc);
      $from["references"] && $email->add_header("References",$from["references"]);
      $from["in-reply-to"] && $email->add_header("In-Reply-To",$from["in-reply-to"]);
      $from["precedence"] && $email->add_header("Precedence",$from["precedence"]);
      
      $email->set_to_address($to_address);
    
      $from_name = $from["name"] or $from_name = $current_user->get_username(1);

      // REMOVE ME!!
      $email->ignore_no_email_urls = true;
      $email->ignore_no_email_hosts = true;

      $hash = $from["hash"];

      $email->set_message_id($hash);
      $subject_extra = "{Key:".$hash."}";

      if ($commentTemplateHeaderID = config::get_config_item("task_email_header")) {
        $commentTemplate = new commentTemplate;
        $commentTemplate->set_id($commentTemplateHeaderID);
        $commentTemplate->select();
        $body_header = $commentTemplate->get_populated_template("task", $this->get_id());
      }
      if ($commentTemplateFooterID = config::get_config_item("task_email_footer")) {
        $commentTemplate = new commentTemplate;
        $commentTemplate->set_id($commentTemplateFooterID);
        $commentTemplate->select();
        $body_footer = $commentTemplate->get_populated_template("task", $this->get_id());
      }

      $subject = commentTemplate::populate_string(config::get_config_item("emailSubject_taskComment"), "task", $this->get_id());
      $email->set_subject($subject . " " . $subject_extra);
      $email->set_body($body_header.$body.$body_footer);
      $email->set_message_type($type);

      if (defined("ALLOC_DEFAULT_FROM_ADDRESS") && ALLOC_DEFAULT_FROM_ADDRESS) {
        $email->set_reply_to("All parties via ".ALLOC_DEFAULT_FROM_ADDRESS);
        $email->set_from($from_name." via ".ALLOC_DEFAULT_FROM_ADDRESS);
      } else {
        $f = $current_user->get_from() or $f = config::get_config_item("allocEmailAdmin");
        $email->set_reply_to($f);
        $email->set_from($f);
      }

      if ($from["commentID"]) {
        $files = get_attachments("comment",$from["commentID"]);
        if (is_array($files)) {
          foreach ($files as $file) {
            $email->add_attachment($file["path"]);
          }
        }
      }

      if ($email->send(false)) {
        return $successful_recipients;
      }
    }   
  }

  function add_comment_from_email($email) {
 
    // Skip over emails that are from alloc. These emails are kept only for
    // posterity and should not be parsed and downloaded and re-emailed etc.
    if (same_email_address($email->mail_headers->fromaddress, ALLOC_DEFAULT_FROM_ADDRESS)) {
      $email->mark_seen();
      return;
    }

    // Make a new comment
    $comment = new comment;
    $comment->set_value("commentType","task");
    $comment->set_value("commentLinkID",$this->get_id());
    $comment->set_value("commentEmailUID",$email->msg_uid);
    $comment->save();
    $commentID = $comment->get_id();

    // Save the email attachments into a directory
    $dir = ATTACHMENTS_DIR."comment".DIRECTORY_SEPARATOR.$comment->get_id();
    if (!is_dir($dir)) {
      mkdir($dir, 0777);
    }
    $file = $dir.DIRECTORY_SEPARATOR."mail.eml";
    $decoded = $email->save_email($file);

    // Try figure out and populate the commentCreatedUser/commentCreatedUserClientContactID fields
    list($from_address,$from_name) = parse_email_address($decoded[0]["Headers"]["from:"]);

    $person = new person;
    $personID = $person->find_by_name($from_name);
    $personID or $personID = $person->find_by_email($from_address);

    if ($personID && (!is_object($current_user) || (is_object($current_user) && !$current_user->get_id()))) {
      global $current_user;
      $current_user = new person;
      $current_user->load_current_user($personID);
    }

    $cc = new clientContact();
    $clientContactID = $cc->find_by_name($from_name, $this->get_value("projectID"));
    $clientContactID or $clientContactID = $cc->find_by_email($from_address, $this->get_value("projectID"));

    if ($personID) {
      $comment->set_value('commentCreatedUser', $personID);
    } else if ($clientContactID) {
      $comment->set_value('commentCreatedUserClientContactID', $clientContactID);
    }

    // If we don't have a $from_name, but we do have a personID or clientContactID, get proper $from_name
    if (!$from_name && $personID) {
      $from_name = person::get_fullname($personID);

    } else if (!$from_name && $clientContactID) {
      $cc = new clientContact;
      $cc->set_id($clientContactID);
      $cc->select();
      $from_name = $cc->get_value("clientContactName");

    } else if (!$from_name) {
      $from_name = $from_address;
    }

    // If user wants to un/subscribe to this comment
    $subject = $decoded[0]["Headers"]["subject:"];
    $ip_action = interestedParty::adjust_by_email_subject($subject,"task",$this->get_id(),$from_name,$from_address,$personID,$clientContactID);

    // Load up some variables for later in send_emails()
    $from["email"] = $from_address;
    $from["name"] = $from_name;
    $from["references"] = $decoded[0]["Headers"]["references:"];
    $from["in-reply-to"] = $decoded[0]["Headers"]["in-reply-to:"];
    $from["precedence"] = $decoded[0]["Headers"]["precedence:"];

    // Don't update last modified fields...
    $comment->skip_modified_fields = true;

    // Update comment with the text body and the creator
    $body = trim(mime_parser::get_body_text($decoded));
    $comment->set_value("comment",$body);
    $comment->set_value("commentCreatedUserText",trim($decoded[0]["Headers"]["from:"]));
    $comment->save();
    $from["commentID"] = $comment->get_id();
    $from["parentCommentID"] = $comment->get_id();
    $from["entity"] = "task";
    $from["entityID"] = $this->get_id();

    #$recipients[] = "assignee";
    #$recipients[] = "manager";
    #$recipients[] = "creator";
    $recipients[] = "interested";

    $token = new token;
    if ($token->select_token_by_entity_and_action("task",$comment->get_value("commentLinkID"),"add_comment_from_email")) {
      $from["hash"] = $token->get_value("tokenHash");
    }

    if ($ip_action == "subscribed") {
      $comment->set_value("comment",$from_name." is now a party to this conversation.\n\n".$comment->get_value("comment"));
      $comment->save();
    } else if ($ip_action == "unsubscribed") {
      $comment->set_value("comment",$from_name." is no longer a party to this conversation.\n\n".$comment->get_value("comment"));
      $comment->save();
    }

    if ($ip_action != "unsubscribed") { // no email sent for unsubscription requests
      $successful_recipients = $this->send_emails($recipients,"task_comments",$comment->get_value("comment"),$from);
    } 

    if ($successful_recipients) {
      $comment->set_value("commentEmailRecipients",$successful_recipients);
      $comment->save();
    }
  }

  function get_changes_list() {
    // This function returns HTML rows for the changes that have been made to this task
    $rows = array();

    $people_cache = get_cached_table("person");

    $options = array("return"       => "array"
                    ,"entityType"   => "task"
                    ,"entityID"     => $this->get_id());
    $changes = auditItem::get_list($options);

    // we record changes to taskName, taskDescription, priority, timeEstimate, projectID, dateActualCompletion, dateActualStart, dateTargetStart, dateTargetCompletion, personID, managerID, parentTaskID, taskTypeID, duplicateTaskID
    foreach($changes as $auditItem) {
      $changeDescription = "";
      $oldValue = $auditItem->get_value('oldValue');
      if($auditItem->get_value('changeType') == 'FieldChange') {
        $newValue = $auditItem->get_new_value();
        switch($auditItem->get_value('fieldName')) {
          case 'taskName':
            $changeDescription = "Task name changed from '$oldValue' to '$newValue'.";
            break;
          case 'taskDescription':
            $changeDescription = "Task description changed. <a class=\"magic\" href=\"#x\" onclick=\"$('#auditItem" . $auditItem->get_id() . "').slideToggle('fast');\">Show</a> <div class=\"hidden\" id=\"auditItem" . $auditItem->get_id() . "\"><div><b>Old Description</b><br>" . page::to_html($oldValue) . "</div><div><b>New Description</b><br>" . page::to_html($newValue) . "</div></div>";
            break;
          case 'priority':
            $priorities = config::get_config_item("taskPriorities");
            $changeDescription = "Task priority changed from " . $priorities[$oldValue]["label"] . " to " . $priorities[$newValue]["label"] . ".";
            $changeDescription = sprintf('Task priority changed from <span style="color: %s;">%s</span> to <span style="color: %s;">%s</span>.', $priorities[$oldValue]["colour"], $priorities[$oldValue]["label"], $priorities[$newValue]["colour"], $priorities[$newValue]["label"]);
          break;
          case 'projectID':
            task::load_entity("project", $oldValue, $oldProject);
            task::load_entity("project", $newValue, $newProject);
            is_object($oldProject) and $oldProjectLink = $oldProject->get_project_link();
            is_object($newProject) and $newProjectLink = $newProject->get_project_link();
            $oldProjectLink or $oldProjectLink = "&lt;empty&gt;";
            $newProjectLink or $newProjectLink = "&lt;empty&gt;";
            $changeDescription = "Project changed from ".$oldProjectLink." to ".$newProjectLink.".";
          break;
          case 'parentTaskID':
            task::load_entity("task", $oldValue, $oldTask);
            task::load_entity("task", $newValue, $newTask);
            if(!$oldValue && is_object($newTask)) {
              $changeDescription = sprintf("Task was set to a child of %d %s.", $newTask->get_id(), $newTask->get_task_link());
            } else if(!$newValue && is_object($oldTask)) {
              $changeDescription = sprintf("Task ceased to be a child of %d %s", $oldTask->get_id(), $oldTask->get_task_link());
            } else if (is_object($oldTask) && is_object($newTask)) {
              $changeDescription = sprintf("Task ceased to be a child of %d %s and became a child of %d %s.", $oldTask->get_id(), $oldTask->get_task_link(), $newTask->get_id(), $newTask->get_task_link());
            }
          break;
          case 'duplicateTaskID':
            task::load_entity("task", $oldValue, $oldTask);
            task::load_entity("task", $newValue, $newTask);
            if(!$oldValue) {
              $changeDescription = "The task was marked a duplicate of " . $newTask->get_task_link() . ".";
            } elseif(!$newValue) {
              $changeDescription = "Task is no longer a duplicate of " . $oldTask->get_task_link() . ".";
            } else {
              $changeDescription = "Task is no longer a duplicate of " . $oldTask->get_task_link() . " and is now a duplicate of " . $newTask->get_task_link() . ".";
            }
          break;
          case 'personID':
            $changeDescription = "Task was reassigned from " . $people_cache[$oldValue]["name"] . " to " . $people_cache[$newValue]["name"] . ".";
          break;
          case 'managerID':
            $changeDescription = "Task manager changed from " . $people_cache[$oldValue]["name"] . " to " . $people_cache[$newValue]["name"] . ".";
          break;
          case 'taskTypeID':
            $changeDescription = "Task type was changed from " . $oldValue . " to " . $newValue . ".";
          break;
          case 'taskSubStatus':
            $taskStatii = task::get_task_statii_array(true);
            $statusParentNew = task::get_task_status_thing("status","",$newValue);
            $statusParentNewColour = task::get_task_status_thing("colour","",$newValue);
            $statusParentOld = task::get_task_status_thing("status","",$oldValue);
            $statusParentOldColour = task::get_task_status_thing("colour","",$oldValue);
            $changeDescription = sprintf('Task status changed from <span style="%s">%s</span> to <span style="%s">%s</span>.'
                                        ,$statusParentOldColour
                                        ,$taskStatii[$statusParentOld."_".$oldValue]
                                        ,$statusParentNewColour
                                        ,$taskStatii[$statusParentNew."_".$newValue]
                                        );
          break;
          case 'dateActualCompletion':
          case 'dateActualStart':
          case 'dateTargetStart':
          case 'dateTargetCompletion':
          case 'timeEstimate':
            // these four cases are more or less identical
            switch($auditItem->get_value('fieldName')) {
              case 'dateActualCompletion': $fieldDesc = "actual completion date"; break;
              case 'dateActualStart': $fieldDesc = "actual start date"; break;
              case 'dateTargetStart': $fieldDesc = "estimate/target start date"; break;
              case 'dateTargetCompletion': $fieldDesc = "estimate/target completion date"; break;
              case 'timeEstimate': $fieldDesc = "estimated time";
            }
            if(!$oldValue) {
              $changeDescription = "The $fieldDesc was set to $newValue.";
            } elseif(!$newValue) {
              $changeDescription = "The $fieldDesc, previously $oldValue, was removed.";
            } else {
              $changeDescription = "The $fieldDesc changed from $oldValue to $newValue.";
            }
          break;
        }

      // these are the cases in which other tasks are un/marked duplicates of this task
      } elseif($auditItem->get_value('changeType') == 'TaskMarkedDuplicate') {
        task::load_entity("task", $oldValue, $otherTask);
        $changeDescription = "The task " . $otherTask->get_id() . " " . $otherTask->get_task_link() . " was marked a duplicate of this task.";
      } elseif($auditItem->get_value('changeType') == 'TaskUnmarkedDuplicate') {
        task::load_entity("task", $oldValue, $otherTask);
        $changeDescription = "The task " . $otherTask->get_id() . " " . $otherTask->get_task_link() . " was no longer marked a duplicate of this task.";
      } elseif($auditItem->get_value('changeType') == 'TaskClosed') {
        $changeDescription = "The task was closed.";
      } elseif($auditItem->get_value('changeType') == 'TaskReopened') {
        $changeDescription = "The task was opened.";
      }
      $rows[] = "<tr><td class=\"nobr\">" . $auditItem->get_value("dateChanged") . "</td><td>$changeDescription</td><td>" . $people_cache[$auditItem->get_value("personID")]["name"] . "</td></tr>";

    }

    return implode("\n", $rows);
  }

  function load_entity($type, $id, &$entity) {
    // helper function to cut down on code duplication in the above function
    if($id) {
      $entity = new $type;
      $entity->set_id($id);
      $entity->select();
    }
  }

  function audit_insert() {
    $this->mark_reopened();
  }

  function mark_closed() {
    // write a message into the log, closing this task
    $ai = new auditItem();
    $ai->audit_special_change($this, "TaskClosed");
    $ai->insert();
  }

  function mark_reopened() {
    $ai = new auditItem();
    $ai->audit_special_change($this, "TaskReopened");
    $ai->insert();
  }

  function update_search_index_doc(&$index) {
    $p = get_cached_table("person");
    $creatorID = $this->get_value("creatorID");
    $creator_field = $creatorID." ".$p[$creatorID]["username"]." ".$p[$creatorID]["name"];
    $closerID = $this->get_value("closerID");
    $closer_field = $closerID." ".$p[$closerID]["username"]." ".$p[$closerID]["name"];
    $personID = $this->get_value("personID");
    $person_field = $personID." ".$p[$personID]["username"]." ".$p[$personID]["name"];
    $managerID = $this->get_value("managerID");
    $manager_field = $managerID." ".$p[$managerID]["username"]." ".$p[$managerID]["name"];
    $taskModifiedUser = $this->get_value("taskModifiedUser");
    $taskModifiedUser_field = $taskModifiedUser." ".$p[$taskModifiedUser]["username"]." ".$p[$taskModifiedUser]["name"];
    $status = $this->get_value("taskStatus");
    $this->get_value("taskSubStatus") and $status.= " ".$this->get_value("taskSubStatus");

    if ($this->get_value("projectID")) {
      $project = new project();
      $project->set_id($this->get_value("projectID"));
      $project->select();
      $projectName = $project->get_name();
      $projectShortName = $project->get_name(array("showShortProjectLink"=>true));
      $projectShortName && $projectShortName != $projectName and $projectName.= " ".$projectShortName;
    }

    $doc = new Zend_Search_Lucene_Document();
    $doc->addField(Zend_Search_Lucene_Field::Keyword('id'   ,$this->get_id()));
    $doc->addField(Zend_Search_Lucene_Field::Text('name'    ,$this->get_value("taskName")));
    $doc->addField(Zend_Search_Lucene_Field::Text('project' ,$projectName));
    $doc->addField(Zend_Search_Lucene_Field::Text('pid'     ,$this->get_value("projectID")));
    $doc->addField(Zend_Search_Lucene_Field::Text('creator' ,$creator_field));
    $doc->addField(Zend_Search_Lucene_Field::Text('closer'  ,$closer_field));
    $doc->addField(Zend_Search_Lucene_Field::Text('assignee',$person_field));
    $doc->addField(Zend_Search_Lucene_Field::Text('manager' ,$manager_field));
    $doc->addField(Zend_Search_Lucene_Field::Text('modifier',$taskModifiedUser_field));
    $doc->addField(Zend_Search_Lucene_Field::Text('desc'    ,$this->get_value("taskDescription")));
    $doc->addField(Zend_Search_Lucene_Field::Text('priority',$this->get_value("priority")));
    $doc->addField(Zend_Search_Lucene_Field::Text('estimate',$this->get_value("timeEstimate")));
    $doc->addField(Zend_Search_Lucene_Field::Text('type',$this->get_value("taskTypeID")));
    $doc->addField(Zend_Search_Lucene_Field::Text('status',$status));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateCreated',str_replace("-","",$this->get_value("dateCreated"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateAssigned',str_replace("-","",$this->get_value("dateAssigned"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateClosed',str_replace("-","",$this->get_value("dateClosed"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateTargetStart',str_replace("-","",$this->get_value("dateTargetStart"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateTargetCompletion',str_replace("-","",$this->get_value("dateTargetCompletion"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateStart',str_replace("-","",$this->get_value("dateActualStart"))));
    $doc->addField(Zend_Search_Lucene_Field::Text('dateCompletion',str_replace("-","",$this->get_value("dateActualCompletion"))));
    $index->addDocument($doc);
  }

}


?>
