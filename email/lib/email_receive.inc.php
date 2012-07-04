<?php

/*
 * Copyright (C) 2006-2011 Alex Lance, Clancy Malcolm, Cyber IT Solutions
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

class email_receive {

  var $host;
  var $port;
  var $username;
  var $password;
  var $protocol;
  var $lockfile;
  var $mbox;
  var $connection;
  var $mail_headers;
  var $mail_structure;
  var $mail_text;
  var $mail_parts;
  var $mail_info;
  var $dir;
  var $mime_types = array("text", "multipart", "message", "application", "audio", "image", "video", "other");
  
  function __construct($info,$lockfile=false) {
    $this->host     = $info["host"];
    $this->port     = $info["port"];
    $this->username = $info["username"];
    $this->password = $info["password"];
    $this->protocol = $info["protocol"] or $this->protocol = "imap";
    $this->lockfile = $lockfile;

    // Nuke lock files that are more that 30 min old 
    if ($this->lockfile && file_exists($this->lockfile) && (time() - filemtime($this->lockfile)) > 1800) {
      $this->unlock();
    } 

    if ($this->lockfile && file_exists($this->lockfile)) {
      alloc_error("Mailbox is locked. Remove ".$this->lockfile." to unlock.");
    } else if ($this->lockfile) {
      $this->lock();
    }
  }
  
  function open_mailbox($folder="",$ops=OP_HALFOPEN, $fatal=true) {
    if ($this->connection) {
      imap_close($this->connection); 
    }
    $this->connect_string = '{'.$this->host.':'.$this->port.'/'.$this->protocol.config::get_config_item("allocEmailExtra").'}';
    $this->connection = imap_open($this->connect_string, $this->username, $this->password, $ops);
    if (!$this->connection && $fatal) {
      alloc_error("Unable to access mail folder(1).");
    }
    $list = imap_list($this->connection, $this->connect_string, "*");
    if (!is_array($list) || !count($list)) { // || !in_array($connect_string.$folder,$list)) {
      $this->unlock();
      imap_close($this->connection); 
      if ($fatal) {
        alloc_error("Unable to access mail folder(2).");
      }
    } else {
      $rtn = imap_reopen($this->connection, $this->connect_string.$folder);

      $errs = print_r(imap_errors(),1);
      if (!$rtn || preg_match("/Invalid mailbox name/i",$errs) || preg_match("/Mailbox does not exist/i",$errs)) {
        $rtn = imap_reopen($this->connection, $this->connect_string.str_replace("/",".",$folder));
      }

      if (!$rtn) {
        imap_close($this->connection); 
        if ($fatal) {
          alloc_error("Unable to access mail folder(3).");
        }
      }
    }
    if (!$rtn && $fatal) {
      alloc_error("<pre>IMAP errors: ".print_r(imap_errors(),1).print_r(imap_alerts(),1)."</pre>");
    }
    return $rtn;
  }

  function get_num_emails() {
    if (!$this->mail_info) {
      $this->check_mail();
    }
    if (is_object($this->mail_info)) {
      return $this->mail_info->messages;
    }
  }

  function get_num_new_emails() {
    if (!$this->mail_info) {
      $this->check_mail();
    }
    if (is_object($this->mail_info)) {
      return $this->mail_info->unseen;
    }
  }

  function check_mail() {
    if ($this->connection) {
      $this->mail_info = imap_status($this->connection, $this->connect_string, SA_ALL);
    } else { 
      unset($this->mail_info);
    }
  }

  function create_mailbox($name) {
    $name = $this->connect_string.$name;
    if (!imap_status($this->connection,$name,SA_ALL)) {
      return imap_createmailbox($this->connection, imap_utf7_encode($name));
    }
  }

  function move_mail($uid,$mailbox) {
    return imap_mail_move($this->connection, $uid, $mailbox ,CP_UID);
  }

  function get_new_email_msg_uids() {
    return imap_search($this->connection,"UNSEEN",SE_UID);
  }

  function get_all_email_msg_uids() {
    return imap_search($this->connection,"ALL",SE_UID);
  }

  function get_emails_UIDs_search($str) {
    return imap_search($this->connection,$str, SE_UID);
  }

  function set_msg($x) {
    $this->msg_uid = $x;
    $this->mail_headers = $this->mail_structure = $this->mail_text = $this->mail_parts = $this->mail_info = $this->dir = "";
  }

  function set_msg_text($text) {
    $this->set_msg(null);
    $this->msg_text = $text;
  }

  function get_msg_header($uid=0) {
    $uid or $uid = $this->msg_uid;
    if ($uid) {
      $this->mail_headers = $this->parse_headers(imap_fetchheader($this->connection, $uid, FT_UID));
    } else if ($this->msg_text) {
      $bits = preg_split("/\r?\n\r?\n/", $this->msg_text);
      $this->mail_headers = $this->parse_headers($bits[0]);
    }
    return $this->mail_headers;
  }

  function parse_headers($headers="") {
    $lines = preg_split("/\r?\n/", $headers);
    foreach ($lines as $line) {
      // start new header
      if (preg_match('/^[A-Za-z]/', $line)) {
        preg_match('/([^:]+): ?(.*)$/', $line, $matches);
        $newHeader = strtolower($matches[1]);
        $rtn[$newHeader] = trim($matches[2]);
        $currentHeader = $newHeader;

      // continue header
      } else if ($line && $currentHeader) {
        $rtn[$currentHeader] .= " ".trim($line);
      }
    }
    return (array)$rtn;
  }

  function download_email_part($num,$encoding) {
    $raw_data = imap_fetchbody($this->connection, $this->msg_uid, $num, FT_UID | FT_PEEK);
    return $this->decode_part($encoding,$raw_data);
  }

  function load_structure() {
    if ($this->msg_uid && !$this->mail_structure) {
      $this->mail_structure = imap_fetchstructure($this->connection,$this->msg_uid,FT_UID);
    } else if ($this->msg_text) {
      $m = new Mail_mimeDecode($this->msg_text);
      $params['include_bodies'] = true;
      $params['decode_bodies']  = true;
      $params['decode_headers'] = true;
      $this->mail_structure = $m->decode($params);
    }
  }

  function get_raw_email_by_msg_uid($msg_uid) {
    // $result = imap_fetch_overview($this->connection,$msg_uid,FT_UID);
    // only view emails that *have* been seen before otherwise 
    // we might view an email before it has been downloaded by
    // receiveEmail.php
    //if (is_array($result) && $result[0]->seen) { 

    // Now we don't care if it's been seen before, since we're not polling IMAP anymore
    return $this->get_raw_header_and_body($msg_uid);
  }

  function get_raw_header_and_body($msg_uid=false) {
    $msg_uid or $msg_uid = $this->msg_uid;
    static $cache;

    if ($msg_uid) {
      if ($cache[$msg_uid]) {
        return $cache[$msg_uid];
      }
      $header = imap_fetchheader($this->connection,$msg_uid, FT_UID);
      $body = imap_body($this->connection,$msg_uid,FT_UID);
      $cache[$msg_uid] = array($header,$body);
      return $cache[$msg_uid];
    } else if ($this->msg_text) {
      return Mail_mimeDecode::_splitBodyHeader($this->msg_text);
    }
  }

  function save_email($dir) {
    if ($dir && !is_dir($dir)) {
      mkdir($dir, 0777);
    }
    $dir && $dir[strlen($dir)-1] != DIRECTORY_SEPARATOR and $dir.=DIRECTORY_SEPARATOR;
    $this->dir = $dir;

    $this->load_structure();
    $this->load_parts($this->mail_structure);

    foreach ($this->mail_parts as $v) {
      $s = $v["part_object"]; // structure
      $raw_data = imap_fetchbody($this->connection, $this->msg_uid, $v["part_number"],FT_UID | FT_PEEK);
      $thing = $this->decode_part($s->encoding,$raw_data);

      if (!$this->mail_text && strtolower($this->mime_types[$s->type]."/".$s->subtype) == "text/plain") {
        $this->mail_text = $thing;
      } else {
        $filename = $this->get_parameter_attribute_value($s->parameters,"name");
        $filename or $filename = $this->get_parameter_attribute_value($s->parameters,"filename");
        $filename or $filename = $this->get_parameter_attribute_value($s->dparameters,"name");
        $filename or $filename = $this->get_parameter_attribute_value($s->dparameters,"filename");

        //$filename or $filename = $v["part_number"]; // we're only storing attachments that have filenames.
        if ($filename) {
          $fh = fopen($dir.$filename,"wb");
          fputs($fh, $thing);
          fclose($fh);
        }
      }
    } 

    rmdir_if_empty($dir);
  }

  function parse_mime($structure) {
    foreach ((array)$structure->parts as $part) {
      if ($part->disposition == 'attachment') {
        $i++;
        $attachments[$i]['body'] = $part->body;
        unset($name);
        $name = $part->ctype_parameters['name'];
        $name or $name = $part->ctype_parameters['filename'];
        if (property_exists($part,"d_parameters") && is_array($part->d_parameters)) {
          $name or $name = $part->d_parameters["name"];
          $name or $name = $part->d_parameters["filename"];
        }

        $attachments[$i]['name'] = $name;
      } else {
        if(count($part->parts)>0) {
          foreach($part->parts as $sp) {
            if(strpos($sp->headers['content-type'],'text/plain')!==false) {
              $plain = $sp->body;
            }
            if(strpos($sp->headers['content-type'],'text/html')!==false) {
              $html = $sp->body;
            }
          }
        } else {
          if(strpos($part->headers['content-type'],'text/plain')!==false) {
            $plain = $part->body;
          }
          if(strpos($part->headers['content-type'],'text/html')!==false) {
            $html = $part->body;
          }
        }
      }
    }
    if(trim($plain)=='') {
      $plain = $structure->body;
    }
    return array($plain, $attachments);
  }

  function save_email_from_text($text, $dir) {
    if ($dir && !is_dir($dir)) {
      mkdir($dir, 0777);
    }
    $dir && $dir[strlen($dir)-1] != DIRECTORY_SEPARATOR and $dir.=DIRECTORY_SEPARATOR;
    $this->dir = $dir;
    $this->load_structure();
    list($this->mail_text,$attachments) = $this->parse_mime($this->mail_structure);

    foreach ((array)$attachments as $v) {
      if ($v["name"]) {
        $fh = fopen($dir.$v["name"],"wb");
        fputs($fh, $v["body"]);
        fclose($fh);
      }
    }
    rmdir_if_empty($dir);
  }

  function mark_seen() {
    if ($this->msg_uid) {
      imap_setflag_full($this->connection, $this->msg_uid, "\\SEEN", FT_UID); // this doesn't work!
      $body = imap_body($this->connection, $this->msg_uid,FT_UID); // this seems to force it to be marked seen
    }
  }

  function mark_unseen() {
    imap_clearflag_full($this->connection, $this->msg_uid, "\\SEEN", ST_UID);
  }

  function forward($address,$subject,$text='') {
    list($header,$body) = $this->get_raw_header_and_body();
    $header and $header_obj = $this->parse_headers($header);
    $orig_subject = $header_obj["subject"];
    $orig_subject and $s = " [".trim($orig_subject)."]";

    $dir = ATTACHMENTS_DIR.'tmp'.DIRECTORY_SEPARATOR;

    $filename = md5($header.$body);
    $fh = fopen($dir.$filename,"wb");
    fputs($fh, $header.$body);
    fclose($fh);

    $email = new email_send();
    $email->set_from(config::get_config_item("AllocFromEmailAddress"));
    $email->set_subject($subject.$s);
    $email->set_body($text);
    $email->set_to_address($address);
    $email->set_message_type("orphan");
    $email->add_attachment($dir.$filename);
    $email->send(false);

    unlink($dir.$filename);
  }

  function lock() {
    if (is_dir(dirname($this->lockfile)) && is_writeable(dirname($this->lockfile))) {
      $fh = fopen($this->lockfile,"w");
      fputs($fh,date("r"));
      fclose($fh);
    }
  }

  function unlock() {
    if (file_exists($this->lockfile)) {
      unlink($this->lockfile);  
    }
  }

  function close() {
    $this->unlock();
    if ($this->connection) {
      #imap_close($this->connection);
      imap_close($this->connection,CL_EXPUNGE); // expunge messages marked for deletion
    }
  }

  function archive($mailbox=null) {
    $keys = $this->get_hashes();
    $token = new token;
    if ($keys && is_array($keys) && $token->set_hash($keys[0])) {
      if ($token->get_value("tokenEntity") == "comment") {
        $db = new db_alloc();
        $row = $db->qr("SELECT commentMaster,commentMasterID 
                          FROM comment
                         WHERE commentID = %d"
                      ,$token->get_value("tokenEntityID"));
        $m = $row["commentMaster"];
        $mID = $row["commentMasterID"];
        $mailbox = "INBOX/".$m.$mID;
      } else {
        $m = $token->get_value("tokenEntity");
        $mID = $token->get_value("tokenEntityID");
        $mailbox = "INBOX/".$m.$mID;
      }
    }
    $mailbox or $mailbox = "INBOX";

    // Some IMAP servers like dot-separated mail folders, some like slash-separated
    if ($mailbox) { 
      $created = $this->create_mailbox($mailbox);
      $created or $created = $this->create_mailbox(str_replace("/",".",$mailbox));

      if ($this->msg_uid) {
        $moved = $this->move_mail($this->msg_uid,$mailbox);
        $moved or $moved = $this->move_mail($this->msg_uid,str_replace("/",".",$mailbox));

      } else if ($this->msg_text) {
        $appended = $this->append($mailbox,$this->msg_text);
        $appended or $appended = $this->append(str_replace("/",".",$mailbox),$this->msg_text);
      }
    }
  }

  function append($mailbox,$text) {
    return imap_append($this->connection,$this->connect_string.$mailbox,$text);
  }

  function delete($x=0) {
    #return;
    $x or $x = $this->msg_uid;
    if ($this->connection) {
      imap_delete($this->connection, $x, FT_UID);
    }
  }

  function expunge() {
    imap_expunge($this->connection);
  }

  function get_hashes($headers=false) {
    $headers or $headers = $this->mail_headers;
    $keys = array();

    if (preg_match("/\{Key:[A-Za-z0-9]{8}/i",$headers["subject"],$m)) {
      $key = $m[0];
      $key = str_replace("{Key:","",$key);
      $key and $keys[] = $key;
    }

    $str = $headers["in-reply-to"]." ".$headers["references"];

    preg_match_all("/([A-Za-z0-9]{8})@/",$str,$m);

    if (is_array($m[1])) {
      $temp = array_flip($m[1]);// unique pls
      foreach ($temp as $k => $v) {
        $keys[] = $k;
      }
    }

    return array_unique((array)$keys);
  }

  function get_commands($commands=array()) {
    list($header,$body) = $this->get_raw_header_and_body();
    $header and $header_obj = $this->parse_headers($header);
    $subject = $header_obj["subject"];
    
    $e = new email_send();
    $e->set_headers($header);
  

    $str = $header_obj["in-reply-to"]." ".$header_obj["references"];
    preg_match_all("/\.alloc\.key\.([A-Za-z0-9]{8})@/",$str,$m);
    if (is_array($m[1])) {
      $temp = array_flip($m[1]);// unique pls
      foreach ($temp as $k => $v) {
        $rtn["key"][] = $k;
      }
    }

    // We now have: $header,$body,$subject
    if ($commands) {
      /* Disabled ...
      // Look for commands in the email's header
      foreach ($commands as $k=>$v) {
        $h = trim($e->get_header("x-alloc-".$k));
        $h and $rtn[strtolower($k)][] = $h;
      }

      // Look for commands in the email's body
      $lines = explode("\n",$this->mail_text);

      #echo "Lines: ".print_r($lines,1);
      #echo "<br>Com:".print_r($commands,1);

      // Loop through the email backwards, we're looking for the final paragraph of commands
      for($i=count($lines);$i>0;$i--){
        #echo "<br>line: [".$i."]:".$lines[$i]." go:".sprintf("%d",$go)." starting_line: ".$starting_line;
        $lines[$i] and $go = true;
        $go && !$lines[$i] and $starting_line = $i;
      }

      // Loop forwards and accumulate the commands
      for($i=$starting_line-1;$i<=count($lines);$i++) {
        foreach ($commands as $k=>$v) {
          preg_match("/^".$k.":\s*(.*)$/i",$lines[$i],$matches);
          if ($matches[1]) {
            $rtn[strtolower($k)][] = trim($matches[1]);
          }
        }
      }
      */ 
      // Look for commands in the email's subject line
      preg_match("/{(Key:[^}]*)}/i",$subject,$matches);
      $subject = $matches[1];
      $bits = explode("^",$subject);
      foreach ($bits as $bit) {
        if ($bit) {
          // ^something: value
          $chunks = explode(":",$bit);
          $key = trim(array_shift($chunks));
          $val = trim(implode(":",$chunks)); 
          if ($commands[strtolower($key)] && $val) {
            $rtn[strtolower($key)][] = trim($val);
          }
        }
      }
    }
  
    // we can have multiple time: entries in an email
    // all other commands are only used once per email
    foreach ((array)$rtn as $k => $v) {
      if ($k == "time") {
        $r[$k] = $v;
      } else {
        $r[$k] = end($v);
      }
    }

    return (array)$r; 
  }
  
  function decode_part($encoding, $thing) {
    if ($encoding == 0) { 
      // 7bit
    } else if ($encoding == 1) { 
      // 8bit
    } else if ($encoding == 2) { 
      // 8bit
    } else if ($encoding == 3) { 
      $thing = imap_base64($thing);  // Decodes base64
    } else if ($encoding == 4) { 
      $thing = imap_qprint($thing);  // quoted-printable to 8bit
    } else if ($encoding == 5) { 
      // ietf-token
    }
    return $thing;
  }

  function load_parts($struct) {
    if (!$this->mail_parts) {
      if (sizeof($struct->parts) > 0) {
        foreach ($struct->parts as $count => $part) {
          $this->add_part_to_array($part, ($count+1));
        }
      // Email does not have a seperate mime attachment for text
      } else {
        $this->mail_parts[] = array('part_number'=>'1', 'part_object'=>$struct);
      }
    }
    return $this->mail_parts;
  }

  function add_part_to_array($struct, $partno) {
    $this->mail_parts[] = array('part_number'=>$partno, 'part_object'=>$struct);

    // Check to see if the part is an attached email message, as in the RFC-822 type
    if ($struct->type == 2) {
      if (sizeof($struct->parts) > 0) {
        foreach ($struct->parts as $count => $part) {

          // Iterate here again to compensate for the broken way that imap_fetchbody() handles attachments
          if (sizeof($part->parts) > 0) {
            foreach ($part->parts as $count2 => $part2) {
              $this->add_part_to_array($part2, $partno.".".($count2+1));
            }

          // Attached email does not have a seperate mime attachment for text
          } else {
            $this->mail_parts[] = array('part_number'=>$partno.'.'.($count+1), 'part_object'=>$struct);
          }
        }

      // Not sure if this is possible
      } else {
        $this->mail_parts[] = array('part_number'=>$prefix.'.1', 'part_object'=>$struct);
      }
    // If there are more sub-parts, expand them out.
    } else {
      if (sizeof($struct->parts) > 0) {
        foreach ($struct->parts as $count => $p) {
          $this->add_part_to_array($p, $partno.".".($count+1));
        }
      }
    }
  }

  function get_charset() {
    if (!$this->mail_structure) {
      $this->load_structure();
    }

    if (property_exists($this->mail_structure,"parameters")) {
      return $this->get_parameter_attribute_value($this->mail_structure->parameters,"charset");

    } else if (property_exists($this->mail_structure,"ctype_parameters") && is_array($this->mail_structure->ctype_parameters)) {
      return $this->mail_structure->ctype_parameters["charset"];
    }
  }

  function get_parameter_attribute_value($parameters,$needle) {
    foreach ((array)$parameters as $v) {
      if (strtolower($v->attribute) == $needle) {
        $rtn = $v->value;
      }
    }
    return $rtn;
  }

  function get_converted_encoding() {
    // Update comment with the text body and the creator
    $body = trim($this->mail_text);

    // if the email has a different encoding, change it to the DB connection encoding so mysql doesn't choke
    $enc = $this->get_charset();
    if ($enc) {
      $db = new db_alloc;
      $db->connect();
      $body = mb_convert_encoding($body, $db->get_encoding(), $enc);
    }
    return $body;
  }
}


// Tests
if (basename($_SERVER["PHP_SELF"]) == "email_receive.inc.php") {
  define("NO_AUTH",1);
  require_once("alloc.php");
  //require_once("emailsettings.php");

  $num = 30;

  $e = new email_receive($info);
  $e->open_mailbox("INBOX");

  echo "\nNum emails: ".$e->get_num_emails();
  echo "\nNew emails: ".$e->get_num_new_emails();
  echo "\ncheck_mail(): ".str_replace("\n"," ",print_r($e->mail_info,1));
  echo "\nget_new_email_msg_uids(): ".str_replace("\n"," ",print_r($e->get_new_email_msg_uids(),1));
  echo "\nget_all_email_msg_uids(): ".str_replace("\n"," ",print_r($e->get_all_email_msg_uids(),1));
  //exit();
  echo "\nget_emails_UIDs_search(): ".str_replace("\n"," ",print_r($e->get_emails_UIDs_search("SUBJECT alloc"),1));
  //echo "\nget_msg_header(): ".str_replace("\n"," ",print_r($e->get_msg_header($num),1));

  echo "\n";
  $e->set_msg($num);
  $e->load_structure();
  echo "\nload_structure(): ".str_replace(" "," ",print_r($e->mail_structure,1));
  echo "\nget_charset(): ".$e->get_charset();
  //exit();

  list($h,$b) = $e->get_raw_email_by_msg_uid($num);
  //echo "\nget_raw_email_by_msg_uid(): "."HEADER: ".$h."\nBODY: ".$b;

  echo "\nsave_email(): ".$e->save_email("/tmp/email");
  //echo "\nload_parts(): ".print_r($e->mail_parts,1);
  echo "\nmail_text (plaintext version): ".$e->mail_text;
  echo "\nget_commands(): ".print_r($e->get_commands(task::get_exposed_fields()),1);
  echo "\n";
}




?>
