<?php

/*
 *
 * Copyright 2006, Alex Lance, Clancy Malcolm, Cybersource Pty. Ltd.
 * 
 * This file is part of AllocPSA <info@cyber.com.au>.
 * 
 * AllocPSA is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 * 
 * AllocPSA is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * AllocPSA; if not, write to the Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 */

class reminder extends db_entity {
  var $data_table = "reminder";
  var $display_field_name = "reminderSubject";


  function reminder() {
    $this->db_entity();         // Call constructor of parent class
    $this->key_field = new db_text_field("reminderID");
    $this->data_fields = array("reminderType"=>new db_text_field("reminderType"),
                               "reminderLinkID"=>new db_text_field("reminderLinkID"),
                               "personID"=>new db_text_field("personID"),
                               "reminderTime"=>new db_text_field("reminderTime"),
                               "reminderRecuringInterval"=>new db_text_field("reminderRecuringInterval"),
                               "reminderRecuringValue"=>new db_text_field("reminderRecuringValue"),
                               "reminderAdvNoticeSent"=>new db_text_field("reminderAdvNoticeSent"),
                               "reminderAdvNoticeInterval"=>new db_text_field("reminderAdvNoticeInterval"),
                               "reminderAdvNoticeValue"=>new db_text_field("reminderAdvNoticeValue"),
                               "reminderSubject"=>new db_text_field("reminderSubject"), "reminderContent"=>new db_text_field("reminderContent"), "reminderModifiedTime"=>new db_text_field("reminderModifiedTime"), "reminderModifiedUser"=>new db_text_field("reminderModifiedUser"));
  }

  // set the modified time to now
  function set_modified_time() {
    $this->set_value("reminderModifiedTime", date("Y-m-d H:i:s"));
  }

  // return just the date of the comment without the time
  function get_modified_date() {
    return substr($this->get_value("reminderModifiedTime"), 0, 10);
  }

  function get_recipients() {
    $db = new db_alloc;
    $recipients = array("-1"=>"-- all --");
    $type = $this->get_value('reminderType');
    if ($type == "project") {
      $query = "SELECT * FROM projectPerson LEFT JOIN person ON projectPerson.personID=person.personID"." WHERE projectPerson.projectID=".$this->get_value('reminderLinkID')
        ." ORDER BY person.username";
    } else if ($type == "task") {
      // Modified query option: to send to all people on the project that this task is from.
      $db->query("SELECT projectID FROM task where taskID=".$this->get_value('reminderLinkID'));
      $db->next_record();

      $query = "SELECT * FROM projectPerson LEFT JOIN person ON projectPerson.personID=person.personID"." WHERE projectPerson.projectID=".$db->f('projectID')
        ." ORDER BY person.username";

    } else {
      $query = "SELECT * FROM person ORDER BY username";
    }
    $db->query($query);
    while ($db->next_record()) {
      $person = new person;
      $person->read_db_record($db);
      $recipients[$person->get_id()] = $person->get_value('username');
    }
    // if(count($recipients) <= 2) {
    // array_shift($recipients);
    // }

    return $recipients;
  }

  function get_recipient_options() {
    global $current_user;
    $fail = false;

    $recipients = $this->get_recipients();
    $type = $this->get_value('reminderType');
    //project reminder
    if ($type == "project") {

    //task reminder
    } else if($type == "task") {
      $task = new task;
      $task->set_id($this->get_value('reminderLinkID'));
      $task->select();
      //get the task assignee
      $recipient = $task->get_value('personID');
      //if the assignee is not part of the project choose the project manager
    } 

    //default -  set to logged in user
    if(!$recipient) {
      if ($this->get_value('personID')) {
        $recipient = $this->get_value('personID');
      } else {
        $recipient = $current_user->get_id();
      }
    }
    return get_options_from_array($recipients, $recipient, true);
  }

  function get_day_options() {
    $days = array("1"=>"1", "2"=>"2", "3"=>"3", "4"=>"4", "5"=>"5", "6"=>"6", "7"=>"7",
                  "8"=>"8", "9"=>"9", "10"=>"10", "11"=>"11", "12"=>"12", "13"=>"13",
                  "14"=>"14", "15"=>"15", "16"=>"16", "17"=>"17", "18"=>"18", "19"=>"19", "20"=>"20", "21"=>"21", "22"=>"22", "23"=>"23", "24"=>"24", "25"=>"25", "26"=>"26", "27"=>"27", "28"=>"28", "29"=>"29", "30"=>"30", "31"=>"31");
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $day = date("d", $date);
    } else {
      $day = date("d", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($days, $day, true);
  }

  function get_month_options() {
    $months = array("1"=>"January", "2"=>"February", "3"=>"March", "4"=>"April", "5"=>"May", "6"=>"June", "7"=>"July", "8"=>"August", "9"=>"September", "10"=>"October", "11"=>"November", "12"=>"December");
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $month = date("m", $date);
    } else {
      $month = date("m", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($months, $month, true);
  }

  function get_year_options() {
    $years = array();
    for ($i = date("Y"); $i < date("Y") + 10; $i++) {
      $years[$i] = $i;
    }
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $year = date("Y", $date);
    } else {
      $year = date("Y", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($years, $year, true);
  }

  function get_hour_options() {
    $hours = array("1"=>"1", "2"=>"2", "3"=>"3", "4"=>"4", "5"=>"5", "6"=>"6", "7"=>"7", "8"=>"8", "9"=>"9", "10"=>"10", "11"=>"11", "12"=>"12");
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $hour = date("h", $date);
    } else {
      $hour = date("h", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($hours, $hour, true);
  }

  function get_minute_options() {
    $minutes = array("0"=>"00", "5"=>"05", "10"=>"10", "15"=>"15", "20"=>"20", "25"=>"25", "30"=>"30", "35"=>"35", "40"=>"40", "45"=>"45", "50"=>"50", "55"=>"55");
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $minute = date("i", $date);
    } else {
      $minute = date("i", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($minutes, $minute, true);
  }

  function get_meridian_options() {
    $meridians = array("am"=>"AM", "pm"=>"PM");
    if ($this->get_value('reminderTime') != "") {
      $date = strtotime($this->get_value('reminderTime'));
      $meridian = date("a", $date);
    } else {
      $meridian = date("a", mktime(date("H"), date("i") + 5 - (date("i") % 5), 0, date("m"), date("d"), date("Y")));
    }
    return get_options_from_array($meridians, $meridian, true);
  }

  function get_recuring_interval_options() {
    $recuring_interval_options = array("Hour"=>"Hour(s)", "Day"=>"Day(s)", "Week"=>"Week(s)", "Month"=>"Month(s)", "Year"=>"Year(s)");
    $recuring_interval = $this->get_value('reminderRecuringInterval');
    if ($recuring_interval == "") {
      $recuring_interval = "Week";
    }
    return get_options_from_array($recuring_interval_options, $recuring_interval, true);
  }

  function get_advnotice_interval_options() {
    $advnotice_interval_options = array("Minute"=>"Minute(s)", "Hour"=>"Hour(s)", "Day"=>"Day(s)", "Week"=>"Week(s)", "Month"=>"Month(s)", "Year"=>"Year(s)");
    $advnotice_interval = $this->get_value('reminderAdvNoticeInterval');
    if ($advnotice_interval == "") {
      $advnotice_interval = "Hour";
    }
    return get_options_from_array($advnotice_interval_options, $advnotice_interval, true);
  }

  function is_alive() {
    $type = $this->get_value('reminderType');
    if ($type == "project") {
      $project = new project;
      $project->set_id($this->get_value('reminderLinkID'));
      if ($project->select() == false || $project->get_value('projectStatus') == "archived") {
        return false;
      }
    } else if ($type == "task") {
      $task = new task;
      $task->set_id($this->get_value('reminderLinkID'));
      if ($task->select() == false || $task->get_value("dateActualCompletion")) {
        return false;
      }
    } else if ($type == "client") {
      $client = new client;
      $client->set_id($this->get_value('reminderLinkID'));
      if ($client->select() == false || $client->get_value('clientStatus') == "archived") {
        return false;
      }
    }
    return true;
  }

  // mail out reminder and update to next date if repeating or remove if onceoff
  // checks to make sure that it is the right time to send reminder should be 
  // dome before calling this function
  function mail_reminder() {
    // if no longer alive then dont send, just delete
    if ($this->is_alive() != true) {
      print "Removed out of date reminder #".$this->get_id()."\n";
      $this->delete();
    } else {
      $date = strtotime($this->get_value('reminderTime'));
      // only sent reminder if it is time to send it
      if (date("YmdHis", $date) <= date("YmdHis")) {

        print sprintf("[%s] Reminder #%d: ",date("Y-m-d H:i:s"), $this->get_id());

        $person = new person;
        $person->set_id($this->get_value('personID'));
        $person->select();

        if ($person->get_value('emailAddress') != "") {
          $email = sprintf("%s %s <%s>"
                          , $person->get_value('firstName')
                          , $person->get_value('surname')
                          , $person->get_value('emailAddress'));

          $personSender = new person;
          $personSender->set_id($this->get_value('reminderModifiedUser'));
          $personSender->select();

          $from = "From: AllocPSA <".ALLOC_DEFAULT_FROM_ADDRESS.">";
          $subject = $this->get_value('reminderSubject');
          $content = $this->get_value('reminderContent');

          // update reminder
          if ($this->get_value('reminderRecuringInterval') == "No") {
            if ($this->delete()) {
              mail($email, stripslashes($subject), stripslashes($content), $from);
              print "Send (One time reminder removed)\n";
            }
          } else if ($this->get_value('reminderRecuringValue') != 0) {
            // increment date until after current date .. no point in sending 
            // multiple reminders, one every 5 minutes in order to catch up 
            // if system is shutdown for a while
            $interval = $this->get_value('reminderRecuringValue');

            $date_H = date("H",$date);
            $date_i = date("i",$date);
            $date_s = 0;
            $date_m = date("m",$date);
            $date_d = date("d",$date);
            $date_Y = date("Y",$date);

            $switch = $this->get_value('reminderRecuringInterval');
            while (date("YmdHis", $date) <= date("YmdHis")) {

              switch ($switch) {
                case "Hour":  $date_H = date("H", $date) + $interval;       break;
                case "Day":   $date_d = date("d", $date) + $interval;       break;
                case "Week":  $date_d = date("d", $date) + (7 * $interval); break;
                case "Month": $date_m = date("m", $date) + $interval;       break;
                case "Year":  $date_Y = date("Y", $date) + $interval;       break;
              }

              $newtime = mktime($date_H,$date_i,$date_s,$date_m,$date_d,$date_Y);
              $date = $newtime;
            }
            $this->set_value('reminderTime', date("Y-m-d H:i:s", $newtime));
            // reset advanced notice
            $this->set_value('reminderAdvNoticeSent', 0);
            if ($this->save()) {
              mail($email, stripslashes($subject), stripslashes($content), $from);
              print "Sent (Reminder date updated to next)\n";
            }
          }
        } else {
          print "ERROR: Unable to send email to '".$person->get_value('username')
               ."' (no email address)\n";
        }
      }
    }
  }

  // checks advanced notice time if any and mails advanced notice if it is time
  function mail_advnotice() {
    $date = strtotime($this->get_value('reminderTime'));
    // if no advanced notice needs to be sent then dont bother
    if ($this->get_value('reminderAdvNoticeInterval') != "No" 
    &&  $this->get_value('reminderAdvNoticeSent') == 0) {
      $date = strtotime($this->get_value('reminderTime'));
      $interval = $this->get_value('reminderAdvNoticeValue');
      $switch = $this->get_value('reminderAdvNoticeInterval');

      $date_H = date("H",$date);
      $date_i = date("i",$date);
      $date_s = 0;
      $date_m = date("m",$date);
      $date_d = date("d",$date);
      $date_Y = date("Y",$date);

      switch ($switch) {
        case "Minute": $date_i = date("i", $date) - $interval;       break;
        case "Hour":   $date_H = date("H", $date) - $interval;       break;
        case "Day":    $date_d = date("d", $date) - $interval;       break;
        case "Week":   $date_d = date("d", $date) - (7 * $interval); break;
        case "Month":  $date_m = date("m", $date) - $interval;       break;
        case "Year":   $date_Y = date("Y", $date) - $interval;       break;
      }
  
      $advnotice_time = mktime($date_H,$date_i,$date_s,$date_m,$date_d,$date_Y);  
  
      // only sent advanced notice if it is time to send it
      if (date("YmdHis", $advnotice_time) <= date("YmdHis")) {
        print sprintf("[%s] Send Advanced Notice for Reminder #%d\n"
                     ,date("Y-m-d H:i:s"), $this->get_id());

        $person = new person;
        $person->set_id($this->get_value('personID'));
        $person->select();

        if ($person->get_value('emailAddress') != "") {
          $email = sprintf("%s %s <%s>"
                          ,$person->get_value('firstName')
                          ,$person->get_value('surname')
                          ,$person->get_value('emailAddress'));

          $personSender = new person;
          $personSender->set_id($this->get_value('reminderModifiedUser'));
          $personSender->select();

          $from = "From: AllocPSA <".ALLOC_DEFAULT_FROM_ADDRESS.">";
          $subject = sprintf("Adv Notice: %s"
                            ,$this->get_value('reminderSubject'));
          $content = $this->get_value('reminderContent');
          
          mail($email, stripslashes($subject), stripslashes($content), $from);
          $this->set_value('reminderAdvNoticeSent', 1);
          $this->save();
        } else {
          print "ERROR: Unable to send email to '".$person->get_value('username')
            ."' (no email address)\n";
        }
      }
    }
  }
}



?>
