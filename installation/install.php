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

define("IN_INSTALL_RIGHT_NOW",1);
require_once("../alloc.php");

define("IMG_TICK","<img src=\"".$TPL["url_alloc_images"]."tick.gif\" alt=\"Good\">");
define("IMG_CROSS","<img src=\"".$TPL["url_alloc_images"]."cross.gif\" alt=\"Bad\">");
$TPL["IMG_TICK"] = IMG_TICK;
$TPL["IMG_CROSS"] = IMG_CROSS;

function show_tab_1() {
  $tab = $_GET["tab"] or $tab = $_POST["tab"];
  return $tab == 1 || !$tab;
}
function show_tab_2() {
  $tab = $_GET["tab"] or $tab = $_POST["tab"];
  return $tab == 2;
}
function show_tab_2b() {
  return $_POST["test_db_credentials"];
}
function show_tab_3() {
  $tab = $_GET["tab"] or $tab = $_POST["tab"];
  return $tab == 3;
}
function show_tab_3b() {
  return $_POST["install_db"];
}
function show_tab_4() {
  $tab = $_GET["tab"] or $tab = $_POST["tab"];
  return $tab == 4;
}
function show_tab_4b() {
  return $_POST["submit_stage_4"];
}
function show_tab_4c() {
  return defined("INSTALL_SUCCESS");
}



$default_allocURL = "http://".$_SERVER["SERVER_NAME"].SCRIPT_PATH;

$config_vars = array("ALLOC_DB_NAME"     => array("default"=>"alloc",              "info"=>"Enter a name for the new allocPSA MySQL database")
                    ,"ALLOC_DB_USER"     => array("default"=>"alloc",              "info"=>"Enter the name of the database user that will access the database")
                    ,"ALLOC_DB_PASS"     => array("default"=>"changeme",           "info"=>"Enter that users database password")
                    ,"ALLOC_DB_HOST"     => array("default"=>"localhost",          "info"=>"Enter the name of the host that the database resides on")
                    ,"ATTACHMENTS_DIR"   => array("default"=>"/var/local/alloc/",  "info"=>"Enter the full path to a directory that can be used for file upload storage, 
                                                                                          (The path must be outside the web document root)")
                    ,"allocURL"          => array("default"=>$default_allocURL,    "info"=>"Enter the base URL that people will use to access allocPSA, eg: http://example.com/alloc/")
                    );


foreach($config_vars as $name => $arr) {
  $val = $_POST[$name] or $val = $_GET[$name];
  $val == "" && !isset($_GET[$name]) && !isset($_POST[$name]) and $val = $arr["default"];
  $name == "ATTACHMENTS_DIR" && $val && !preg_match("/\/$/",$val) and $val.= "/";
  $name == "allocURL" && $val && !preg_match("/\/$/",$val) and $val.= "/";
  $_FORM[$name] = $val;
  $get[] = $name."=".urlencode($val);
  $hidden[] = "<input type='hidden' name='".$name."' value='".$val."'>";
  $TPL[$name] = $val;
}
$TPL["get"] = "&".implode("&",$get);
$TPL["hidden"] = implode("\n",$hidden);

if ($_FORM["ALLOC_DB_USER"] && $_FORM["ALLOC_DB_NAME"]) {
  $db = new db($_FORM["ALLOC_DB_USER"],$_FORM["ALLOC_DB_PASS"],$_FORM["ALLOC_DB_HOST"],$_FORM["ALLOC_DB_NAME"]);
}

if ($_POST["refresh_tab_1"]) {
  header("Location: ".$TPL["url_alloc_installation"]."?1=1".$TPL["get"]);
  exit;
}


// Finish installation
if ($_POST["submit_stage_4"]) {

  // Create directories under attachment dir and chmod them
  $dirs = $external_storage_directories; // something like array("task","client","project","invoice","comment","backups");
  foreach ($dirs as $dir) {
    $d = $_FORM["ATTACHMENTS_DIR"].$dir;
    if (is_dir($d)) {
      $text_tab_4[] = "Already exists: ".$d;
    } else {
      @mkdir($d,0777);
      if (is_dir($d)) {
        $text_tab_4[] = "Created: ".$d;
      } else {
        $text_tab_4[] = "Unable to create directory: ".$d;
        $failed = 1;
      }
    }

    if (!is_writeable($d)) {
      $text_tab_4[] = "This directory is not writeable by the webserver: ".$d;
      $failed = 1;
    }
  }

  // Create alloc_config.php
  if (file_exists(ALLOC_CONFIG_PATH) && is_writeable(ALLOC_CONFIG_PATH) && filesize(ALLOC_CONFIG_PATH) == 0) {
    $str[] = "<?php";
    foreach ($config_vars as $name => $arr) {
      $name != "allocURL" and $str[] = "define(\"".$name."\",\"".$_FORM[$name]."\");";
    }
    $str[] = "?>";
    $str = implode("\n",$str);
    $fh = fopen(ALLOC_CONFIG_PATH,"w+");
    fputs($fh,$str);
    fclose($fh);

    // Clear PHP file cache
    clearstatcache();

    if (file_exists(ALLOC_CONFIG_PATH) && filesize(ALLOC_CONFIG_PATH) > 0) {
      $text_tab_4[] = "Created ".ALLOC_CONFIG_PATH;
    } else {
      $text_tab_4[] = "Unable to create(1): ".ALLOC_CONFIG_PATH;
      $failed = 1;
    }

  } else {
    $text_tab_4[] = "Unable to create(2): ".ALLOC_CONFIG_PATH;
    $failed = 1;
  }

  if ($failed) {
    $TPL["img_install_result"] = IMG_CROSS;
    $TPL["msg_install_result"] = "The allocPSA installation has not completed successfully.";
  } else {
    define("INSTALL_SUCCESS",1);
    $TPL["img_install_result"] = IMG_TICK;
    $TPL["url_alloc_login"][strlen($TPL["url_alloc_login"])-1] != "?" and $qm = "?";
    $TPL["msg_install_result"] = "The allocPSA installation has completed successfully. <a href=\"".$TPL["url_alloc_login"].$qm."msg=".urlencode("Default login username/password: <b>alloc</b><br>You should change both the username and password of this administrator account ASAP.")."\">Click here</a> and login with the username and password of 'alloc'.";
  }

  $_GET["tab"] = 4;
}


if ($_POST["test_db_credentials"] && is_object($db)) {
  // Test supplied credentials


  $link = $db->connect();
  #@mysql_connect($_FORM["ALLOC_DB_HOST"],$_FORM["ALLOC_DB_USER"],$_FORM["ALLOC_DB_PASS"]);
  if ($link) {
    $text_tab_2b[] = "Successfully connected to MySQL database server as user '".$_FORM["ALLOC_DB_USER"]."'.";

    if ($db->select_db($_FORM["ALLOC_DB_NAME"])) {
      #@mysql_select_db($_FORM["ALLOC_DB_NAME"], $link)
      $text_tab_2b[] = "Successfully connected to database '".$_FORM["ALLOC_DB_NAME"]."'.";
    } else {
      $text_tab_2b[] = "Unable to select database '".$_FORM["ALLOC_DB_NAME"]."'. Ensure it was created. (".mysql_error().").";
      $failed = 1;
    }

    $query = "CREATE TABLE test ( hey int );";
    if ($db->query($query)) {
      $text_tab_2b[] = "Successfully created table 'test'.";
    } else {
      $text_tab_2b[] = "Unable to create table 'test'! (".mysql_error().").";
      $failed = 1;
    }

    $query = "DROP TABLE test;";
    if ($db->query($query)) {
      $text_tab_2b[] = "Successfully deleted table 'test'.";
    } else {
      $text_tab_2b[] = "Unable to delete table 'test'! (".mysql_error().").";
      $failed = 1;
    }

  } else {
    $text_tab_2b[] = "Unable to connect to MySQL database server with supplied credentials! (".mysql_error().").";
    $failed = 1;
  }

  if ($failed) {
    $TPL["img_test_db_result"] = IMG_CROSS;
    $TPL["msg_test_db_result"] = "Database connection test unsuccessful!";
  } else {
    $TPL["img_test_db_result"] = IMG_TICK;
    $TPL["msg_test_db_result"] = "Database connection test successful.";
  }

  $_GET["tab"] = 2;

} else if ($_POST["submit_stage_2"]) {
  $_GET["tab"] = 3;

}


if ($_POST["install_db"] && is_object($db)) {
  unset($failed);
  $link = $db->connect();
  $db->select_db($_FORM["ALLOC_DB_NAME"]);
  #$link = @mysql_connect($_FORM["ALLOC_DB_HOST"],$_FORM["ALLOC_DB_USER"],$_FORM["ALLOC_DB_PASS"]);
  #@mysql_select_db($_FORM["ALLOC_DB_NAME"], $link);

  $files = array("../sql/db_structure.sql","../sql/db_data.sql");

  foreach ($files as $file) {
    list($sql,$comments) = parse_sql_file($file);

    foreach($sql as $q) {
      if (!$db->query($q)) {
        $errors[] = "Error! (".mysql_error().").";
      }
    }   
  }    
  

  // Insert config data
  $query = "INSERT INTO config (name, value, type) VALUES ('allocURL','".$_FORM["allocURL"]."','text')";
  if (!$db->query($query)) {
    $errors[] = "Error! (".mysql_error().").";
  }



  $body = <<<EOD
If you're new to allocPSA, just follow the tabs across left to right at the
top of the page, ie: Clients have Projects &gt; Projects have Tasks &gt; Time
Sheet are billed against Tasks &gt; and the Finance section will help you out
when there are Time Sheets.

Here are the cron jobs from the installation in case you hadn't installed
them yet. You will need to install at least the first one to enable the very
useful automated reminders functionality. 

# Check every 10 minutes for any allocPSA Reminders to send
*/10 * * * * wget -q -O /dev/null {$_FORM["allocURL"]}reminder/sendReminders.php

# Check every 5 minutes for any new emails to import into allocPSA
*/5 * * * * wget -q -O /dev/null {$_FORM["allocURL"]}email/receiveEmail.php

# Send allocPSA Daily Digest emails once a day at 4:35am
35 4 * * * wget -q -O /dev/null {$_FORM["allocURL"]}person/sendEmail.php

# Check for allocPSA Repeating Expenses once a day at 4:40am
40 4 * * * wget -q -O /dev/null {$_FORM["allocURL"]}finance/checkRepeat.php

Please feel free to contact us at Cybersource &lt;info@cyber.com.au&gt; or just use
the forums at http://sourceforge.net/projects/allocpsa/ if you have any questions.

To remove this announcement click on the Tools tab and then click the
Announcements link.
EOD;

  // Insert new announcement
  $query = "INSERT INTO announcement (heading, body, personID,displayFromDate,displayToDate) VALUES (\"Getting Started in allocPSA\",\"".db_esc($body)."\",1,'2000-01-01','2030-01-01')";
  if (!$db->query($query)) {
    $errors[] = "Error! (".mysql_error().").";
  }

  // Insert new person
  $query = sprintf("INSERT INTO person (username,password,personActive,perms) VALUES ('alloc','%s',1,'god,admin,manage,employee')",encrypt_password("alloc"));
  if (!$db->query($query)) {
    $errors[] = "Error! (".mysql_error().").";
  }

  // Insert patch data
  $files = get_patch_file_list();
  foreach ($files as $f) {
    $query = sprintf("INSERT INTO patchLog (patchName, patchDesc, patchDate) VALUES ('%s','','%s')",db_esc($f), date("Y-m-d H:i:s"));
    if (!$db->query($query)) {
      $errors[] = "Error! (".mysql_error().").";
    }
  }


  if (!is_array($errors) && !count($errors)) {
    $text_tab_3b[] = "Database import successful!";
    $res = $db->query("SELECT username FROM person");
    $r = $db->row();
    if (is_array($r)) {
      $text_tab_3b[] = "Admin user '".$r["username"]."' imported successfully!";
    } else {
      $text_tab_3b[] = "Problem importing data. Recommended to manually drop database and try again.";
      $failed = 1;
    }
  } else {
    $text_tab_3b = $errors;
    $failed = 1;
  }

  if ($failed) {
    $TPL["img_install_db_result"] = IMG_CROSS;
    $TPL["msg_install_db_result"] = "Database installation unsuccessful!";
  } else {
    $TPL["img_install_db_result"] = IMG_TICK;
    $TPL["msg_install_db_result"] = "Database installation successful.";
  }
  $_GET["tab"] = 3;

} else if ($_POST["patch_db"]) {
  $_GET["tab"] = 3;

} else if ($_POST["submit_stage_3"]) {
  $_GET["tab"] = 4;
}


// Tab 2 Text
if ($_FORM["ALLOC_DB_NAME"] && $_FORM["ALLOC_DB_USER"]) {
  $text_tab_2a[] = "DROP DATABASE IF EXISTS ".$_FORM["ALLOC_DB_NAME"].";";
  $text_tab_2a[] = "";
  $text_tab_2a[] = "CREATE DATABASE ".$_FORM["ALLOC_DB_NAME"].";";

  if ($_FORM["ALLOC_DB_USER"] != 'root') {
    // grant all on alloc14.* to 'heydiddle'@'localhost' IDENTIFIED BY 'hey';
    $text_tab_2a[] = "";
    $text_tab_2a[] = "GRANT ALL ON ".$_FORM["ALLOC_DB_NAME"].".* TO '".$_FORM["ALLOC_DB_USER"]."'@'".$_FORM["ALLOC_DB_HOST"]."' IDENTIFIED BY '".$_FORM["ALLOC_DB_PASS"]."';";
  }

  $text_tab_2a[] = "";
  $text_tab_2a[] = "FLUSH PRIVILEGES;";

}


// Tab 1 Text
foreach ($config_vars as $name => $arr) {
  $text_tab_1[] = "<tr><td>".$arr["info"]."</td><td><input type='text' name='".$name."' size='30' value='".$_FORM[$name]."'></td></tr>";
}


is_array($text_tab_1) and $TPL["text_tab_1"] = implode("\n",$text_tab_1);
is_array($text_tab_2a) and $TPL["text_tab_2a"] = implode("<br/>",$text_tab_2a);
is_array($text_tab_2b) and $TPL["text_tab_2b"] = implode("<br/>",$text_tab_2b);
is_array($text_tab_3b) and $TPL["text_tab_3b"] = implode("<br/>",$text_tab_3b);
is_array($text_tab_4) and $TPL["text_tab_4"] = implode("<br/>",$text_tab_4);


$tab = $_GET["tab"] or $tab = $_POST["tab"] or $tab = $_FORM["tab"];
$tab == 1 || !$tab and $TPL["tab1"] = " active";
$tab == 2 and $TPL["tab2"] = " active";
$tab == 3 and $TPL["tab3"] = " active";
$tab == 4 and $TPL["tab4"] = " active";


include_template("templates/install.tpl");

?>
