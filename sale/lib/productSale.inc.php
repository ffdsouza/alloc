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


define("PERM_APPROVE_PRODUCT_TRANSACTIONS", 256);
class productSale extends db_entity {
  public $classname = "productSale";
  public $data_table = "productSale";
  public $key_field = "productSaleID";
  public $data_fields = array("clientID"
                             ,"projectID"
                             ,"personID"
                             ,"tfID"
                             ,"status"
                             ,"productSaleCreatedTime"
                             ,"productSaleCreatedUser"
                             ,"productSaleModifiedTime"
                             ,"productSaleModifiedUser"
                             ,"productSaleDate"
                             ,"extRef"
                             ,"extRefDate"
                             );
  public $permissions = array(PERM_APPROVE_PRODUCT_TRANSACTIONS => "approve product transactions");

  function validate() {
    if ($this->get_value("status") == "admin" || $this->get_value("status") == "finished") {
      $orig = new $this->classname;
      $orig->set_id($this->get_id());
      $orig->select();
      $orig_status = $orig->get_value("status");
      if ($orig_status == "allocate" && $this->get_value("status") == "admin") {

      } else if (!$this->have_perm(PERM_APPROVE_PRODUCT_TRANSACTIONS)) {
        $rtn[] = "Unable to save Product Sale, user does not have correct permissions.";
      }
    }
    return parent::validate($rtn);
  }
 
  function is_owner() {
    global $current_user;
    return !$this->get_id()
           || $this->get_value("productSaleCreatedUser") == $current_user->get_id()
           || $this->get_value("personID") == $current_user->get_id();
  } 

  function delete() {
    $db = new db_alloc;
    $query = sprintf("SELECT * 
                        FROM productSaleItem 
                       WHERE productSaleID = %d"
                    , $this->get_id());
    $db->query($query);
    while ($db->next_record()) {
      $productSaleItem = new productSaleItem;
      $productSaleItem->read_db_record($db);
      $productSaleItem->delete();
    }
    $this->delete_transactions();
    return parent::delete();
  }

  function translate_meta_tfID($tfID="") {
    global $TPL;
    
    // The special -1 and -2 tfID's represent META TF, i.e. calculated at runtime
    // -1 == META: Project TF
    if ($tfID == -1) { 
      if ($this->get_value("projectID")) {
        $project = new project();
        $project->set_id($this->get_value("projectID"));
        $project->select();
        $tfID = $project->get_value("cost_centre_tfID");
      }
      if (!$tfID) {
        $TPL["message_bad"][] = "Unable to use META: Project TF. Please ensure the project has a TF set, or adjust the transactions.";
      }

    // -2 == META: Salesperson TF
    } else if ($tfID == -2) {
      if ($this->get_value("personID")) {
        $person = new person();
        $person->set_id($this->get_value("personID")); 
        $person->select();
        $tfID = $person->get_value("preferred_tfID");
        if (!$tfID) {
          $TPL["message_bad"][] = "Unable to use META: Salesperson TF. Please ensure the Saleperson has a Preferred Payment TF.";
        }
      } else {
        $TPL["message_bad"][] = "Unable to use META: Salesperson TF. No product salesperson set.";
      }
    } else if ($tfID == -3) {
      $tfID = $this->get_value("tfID");
      $tfID or $TPL["message_bad"][] = "Unable to use META: Sale TF not set.";
    }
    return $tfID;
  }
  
  function get_productSaleItems() {
    $q = sprintf("SELECT * FROM productSaleItem WHERE productSaleID = %d",$this->get_id());
    $db = new db_alloc();
    $db->query($q);
    $rows = array();
    while($row = $db->row()) {
      $rows[$row["productSaleItemID"]] = $row;
    }
    return $rows;
  }

  function get_amounts() {

    $rows = $this->get_productSaleItems();
    $rows or $rows = array();
    $rtn = array();
  
    foreach ($rows as $row) {
      $productSaleItem = new productSaleItem;
      $productSaleItem->read_row_record($row);
      //$rtn["total_spent"] += $productSaleItem->get_amount_spent();
      //$rtn["total_earnt"] += $productSaleItem->get_amount_earnt();
      //$rtn["total_other"] += $productSaleItem->get_amount_other();
      list($sp,$spcur) = array($productSaleItem->get_value("sellPrice"),$productSaleItem->get_value("sellPriceCurrencyTypeID"));

      $sellPriceCurr[$spcur] += page::money($spcur,$sp,"%m");
      $total_sellPrice += exchangeRate::convert($spcur,$sp);
      $total_margin += $productSaleItem->get_amount_margin();
      $total_unallocated += $productSaleItem->get_amount_unallocated();
    }    

    unset($sep,$label,$show);

    foreach ((array)$sellPriceCurr as $code => $amount) {
      $label.= $sep.page::money($code,$amount,"%s%mo %c");
      $sep = " + ";
      $code != config::get_config_item("currency") and $show = true;
    }
    $show && $label and $sellPrice_label = " (".$label.")";

    $total_sellPrice_plus_gst = $total_sellPrice * (config::get_config_item("taxPercent")/100 +1);

    $rtn["total_sellPrice"] = page::money(config::get_config_item("currency"),$total_sellPrice,"%s%mo %c").$sellPrice_label;
    $rtn["total_sellPrice_plus_gst"] = page::money(config::get_config_item("currency"),$total_sellPrice_plus_gst,"%s%mo %c").$sellPrice_label;
    $rtn["total_margin"] = page::money(config::get_config_item("currency"),$total_margin,"%s%mo %c");
    $rtn["total_unallocated"] = page::money(config::get_config_item("currency"),$total_unallocated,"%s%mo %c");
    $rtn["total_unallocated_number"] = page::money(config::get_config_item("currency"),$total_unallocated,"%mo");

    return $rtn;
  }

  function create_transactions() {
    $rows = $this->get_productSaleItems();
    $rows or $rows = array();
  
    foreach ($rows as $row) {
      $productSaleItem = new productSaleItem;
      $productSaleItem->read_row_record($row);
      $productSaleItem->create_transactions();
    }
  }

  function delete_transactions() {
    $rows = $this->get_productSaleItems();
    $rows or $rows = array();
  
    foreach ($rows as $row) {
      $productSaleItem = new productSaleItem;
      $productSaleItem->read_row_record($row);
      $productSaleItem->delete_transactions();
    }
  }
 
  function move_forwards() {
    global $current_user, $TPL;
    $status = $this->get_value("status");
    $db = new db_alloc();


    if ($this->get_value("clientID")) {
      $c = $this->get_foreign_object("client");
      $taskDesc[] = "Client: ".$c->get_name();
      $taskDesc[] = "";
    }
    $taskDesc[] = "Sale items:";
    $taskDesc[] = "";
    foreach((array)$this->get_productSaleItems() as $psiID => $psi_row) {
      $p = new product();
      $p->set_id($psi_row["productID"]);
      $taskDesc[] = "  * ".$p->get_name();
    }

    $taskDesc[] = "";
    $taskDesc[] = "Refer to the sale in alloc for up-to-date information.";
    $taskDesc = implode("\n",$taskDesc);

    if ($status == "edit") {
      $this->set_value("status", "allocate");
      
      $items = $this->get_productSaleItems();
      foreach ($items as $r) {
        $psi = new productSaleItem();
        $psi->set_id($r["productSaleItemID"]);
        $psi->select();
        if (!$db->qr("SELECT transactionID FROM transaction WHERE productSaleItemID = %d",$psi->get_id())) {
          $psi->create_transactions();
        }
      }

    } else if ($status == "allocate") {
      $this->set_value("status", "admin");

      // from salesperson to admin
      if ($this->get_value("projectID")) {
        $name = "Sale ".$this->get_id().": raise an invoice";
        $q = sprintf("SELECT * FROM task WHERE projectID = %d AND taskName = '%s'",$this->get_value("projectID"),db_esc($name));
        if (config::for_cyber() && !$db->qr($q)) {
          $task = new task();
          $task->set_value("projectID",59); // Cyber Admin Project
          $task->set_value("taskName",$name);
          $task->set_value("managerID",$this->get_value("personID")); // salesperson
          $task->set_value("personID",67); // Cyber Support people (jane)
          $task->set_value("priority",3);
          $task->set_value("taskTypeID","Task");
          $task->set_value("taskDescription",$taskDesc);
          $task->save();
          $TPL["message_good"][] = "Task created: ".$task->get_id()." ".$task->get_value("taskName");

          $p1 = new person();
          $p1->set_id($this->get_value("personID"));
          $p1->select();
          $p2 = new person();
          $p2->set_id(67);
          $p2->select();
          $recipients[$p1->get_value("emailAddress")] = array("name"=>$p1->get_name()); 
          $recipients[$p2->get_value("emailAddress")] = array("name"=>$p2->get_name()); 

          $comment = $p2->get_name().",\n\n".$name."\n\n".$taskDesc;
          $commentID = comment::add_comment("task", $task->get_id(), $comment, "task", $task->get_id());
          $emailRecipients = comment::add_interested_parties($commentID, null, $recipients);

          // Re-email the comment out, including any attachments
          if (!comment::send_comment($commentID,$emailRecipients)) {
            $TPL["message"][] = "Email failed to send.";
          } else {
            $TPL["message_good"][] = "Emailed task comment to ".$p1->get_value("emailAddress").", ".$p2->get_value("emailAddress").".";
          }

        }
      }

    } else if ($status == "admin" && $this->have_perm(PERM_APPROVE_PRODUCT_TRANSACTIONS)) {
      $this->set_value("status", "finished");
      if ($_REQUEST["changeTransactionStatus"]) {
        $rows = $this->get_productSaleItems();
        foreach ($rows as $row) {
          $ids[] = $row["productSaleItemID"];
        }
        if ($ids) {
          $ids = esc_implode(",",$ids);
          $q = sprintf("UPDATE transaction SET status = '%s' WHERE productSaleItemID in (%s)",db_esc($_REQUEST["changeTransactionStatus"]),$ids);
          $db = new db_alloc();
          $db->query($q);
        }
      }

      if ($this->get_value("projectID")) {

        // from salesperson to admin
        $name = "Sale ".$this->get_id().": pay the supplier";
        $q = sprintf("SELECT * FROM task WHERE projectID = %d AND taskName = '%s'",$this->get_value("projectID"),db_esc($name));
        if (config::for_cyber() && !$db->qr($q)) {
          $task = new task();
          $task->set_value("projectID",59); // Cyber Admin Project
          $task->set_value("taskName",$name);
          $task->set_value("managerID",$this->get_value("personID")); // salesperson
          $task->set_value("personID",67); // Cyber Support people
          $task->set_value("priority",3);
          $task->set_value("taskTypeID","Task");
          $task->set_value("taskDescription",$taskDesc);
          $task->save();
          $TPL["message_good"][] = "Task created: ".$task->get_id()." ".$task->get_value("taskName");

          $p1 = new person();
          $p1->set_id($this->get_value("personID"));
          $p1->select();
          $p2 = new person();
          $p2->set_id(67);
          $p2->select();
          $recipients[$p1->get_value("emailAddress")] = array("name"=>$p1->get_name()); 
          $recipients[$p2->get_value("emailAddress")] = array("name"=>$p2->get_name()); 

          $comment = $p2->get_name().",\n\n".$name."\n\n".$taskDesc;
          $commentID = comment::add_comment("task", $task->get_id(), $comment, "task", $task->get_id());
          $emailRecipients = comment::add_interested_parties($commentID, null, $recipients);

          // Re-email the comment out, including any attachments
          if (!comment::send_comment($commentID,$emailRecipients)) {
            $TPL["message"][] = "Email failed to send.";
          } else {
            $TPL["message_good"][] = "Emailed task comment to ".$p1->get_value("emailAddress").", ".$p2->get_value("emailAddress").".";
          }
        }

        // from admin to salesperson
        $name = "Sale ".$this->get_id().": place an order to the supplier";
        $q = sprintf("SELECT * FROM task WHERE projectID = %d AND taskName = '%s'",$this->get_value("projectID"),db_esc($name));
        if (config::for_cyber() && !$db->qr($q)) {
          $task = new task();
          $task->set_value("projectID",59); // Cyber Admin Project
          $task->set_value("taskName",$name);
          $task->set_value("managerID",67); // Cyber Support people
          $task->set_value("personID",$this->get_value("personID")); // salesperson
          $task->set_value("priority",3);
          $task->set_value("taskTypeID","Task");
          $task->set_value("taskDescription",$taskDesc);
          $task->save();
          $TPL["message_good"][] = "Task created: ".$task->get_id()." ".$task->get_value("taskName");

          $p1 = new person();
          $p1->set_id($this->get_value("personID"));
          $p1->select();
          $p2 = new person();
          $p2->set_id(67);
          $p2->select();
          $recipients[$p1->get_value("emailAddress")] = array("name"=>$p1->get_name()); 
          $recipients[$p2->get_value("emailAddress")] = array("name"=>$p2->get_name()); 

          $comment = $p2->get_name().",\n\n".$name."\n\n".$taskDesc;
          $commentID = comment::add_comment("task", $task->get_id(), $comment, "task", $task->get_id());
          $emailRecipients = comment::add_interested_parties($commentID, null, $recipients);

          // Re-email the comment out, including any attachments
          if (!comment::send_comment($commentID,$emailRecipients)) {
            $TPL["message"][] = "Email failed to send.";
          } else {
            $TPL["message_good"][] = "Emailed task comment to ".$p1->get_value("emailAddress").", ".$p2->get_value("emailAddress").".";
          }
        }

        // from admin to salesperson
        $name = "Sale ".$this->get_id().": action this sale";
        $q = sprintf("SELECT * FROM task WHERE projectID = %d AND taskName = '%s'",$this->get_value("projectID"),db_esc($name));
        if (config::for_cyber() && !$db->qr($q)) {
          $task = new task();
          $task->set_value("projectID",59); // Cyber Admin Project
          $task->set_value("taskName",$name);
          $task->set_value("managerID",67); // Cyber Support people
          $task->set_value("personID",$this->get_value("personID")); // salesperson
          $task->set_value("priority",3);
          $task->set_value("taskTypeID","Task");
          $task->set_value("taskDescription",$taskDesc);
          $task->save();
          $TPL["message_good"][] = "Task created: ".$task->get_id()." ".$task->get_value("taskName");

          $p1 = new person();
          $p1->set_id($this->get_value("personID"));
          $p1->select();
          $p2 = new person();
          $p2->set_id(67);
          $p2->select();
          $recipients[$p1->get_value("emailAddress")] = array("name"=>$p1->get_name()); 
          $recipients[$p2->get_value("emailAddress")] = array("name"=>$p2->get_name()); 

          $comment = $p2->get_name().",\n\n".$name."\n\n".$taskDesc;
          $commentID = comment::add_comment("task", $task->get_id(), $comment, "task", $task->get_id());
          $emailRecipients = comment::add_interested_parties($commentID, null, $recipients);

          // Re-email the comment out, including any attachments
          if (!comment::send_comment($commentID,$emailRecipients)) {
            $TPL["message"][] = "Email failed to send.";
          } else {
            $TPL["message_good"][] = "Emailed task comment to ".$p1->get_value("emailAddress").", ".$p2->get_value("emailAddress").".";
          }
        }
      }
    }
  }

  function get_transactions($productSaleItemID=false) {
    $rows = array();
    $query = sprintf("SELECT transaction.*
                            ,productCost.productCostID  as pc_productCostID
                            ,productCost.amount         as pc_amount
                            ,productCost.isPercentage   as pc_isPercentage
                            ,productCost.currencyTypeID as pc_currency
                        FROM transaction 
                   LEFT JOIN productCost on transaction.productCostID = productCost.productCostID
                       WHERE productSaleID = %d
                         AND productSaleItemID = %d
                    ORDER BY transactionID"
                    ,$this->get_id()
                    ,$productSaleItemID);
    $db = new db_alloc();
    $db->query($query);
    while ($row = $db->row()) {
      if ($row["transactionType"] == "tax") {
        $row["saleTransactionType"] = "tax";
      } else if ($row["pc_productCostID"]) {
        $row["saleTransactionType"] = $row["pc_isPercentage"] ? "aPerc" : "aCost";
      } else if (!$done && $row["transactionType"] == "sale" && !$row["productCostID"]) {
        $done = true;
        $row["saleTransactionType"] = "sellPrice";
      }
      $rows[] = $row;
    }
    return $rows;
  }

  function move_backwards() {
    global $current_user;

    if ($this->get_value("status") == "finished" && $current_user->have_role("admin")) {
      $this->set_value("status", "admin");

    } else if ($this->get_value("status") == "admin" && $current_user->have_role("admin")) {
      $this->set_value("status", "allocate");

    } else if ($this->get_value("status") == "allocate") {
      $this->set_value("status", "edit");
    }
  }

  function get_list_filter($filter=array()) {
    if ($filter["projectID"]) {
      $sql[] = sprintf("(productSale.projectID = %d)",$filter["projectID"]);
    }
    if ($filter["clientID"]) {
      $sql[] = sprintf("(productSale.clientID = %d)",$filter["clientID"]);
    }
    if ($filter["personID"]) {
      $sql[] = sprintf("(productSale.personID = %d)",$filter["personID"]);
    }

    if (is_array($filter['status'])) {
      $statusArray = $filter['status'];
    } else {
      $statusArray[] = $filter['status'];
    }
    foreach ((array)$statusArray as $status) {
      $status and $subsql[] = sprintf("(productSale.status = '%s')",db_esc($status));
    }
    $subsql and $sql[] = '('.implode(" OR ",$subsql).')';
    
    return $sql;
  }

  function get_list($_FORM=array()) {

    $filter = productSale::get_list_filter($_FORM);

    $debug = $_FORM["debug"];
    $debug and print "\n<pre>_FORM: ".print_r($_FORM,1)."</pre>";
    $debug and print "\n<pre>filter: ".print_r($filter,1)."</pre>";

    if (is_array($filter) && count($filter)) {
      $f = " WHERE ".implode(" AND ",$filter);
    }

    $f.= " ORDER BY IFNULL(productSaleDate,productSaleCreatedTime)";

    $db = new db_alloc();
    $query = sprintf("SELECT productSale.*, project.projectName, client.clientName
                        FROM productSale 
                   LEFT JOIN client ON productSale.clientID = client.clientID
                   LEFT JOIN project ON productSale.projectID = project.projectID
                    ".$f);
    $db->query($query);
    $statii = productSale::get_statii();
    $people = get_cached_table("person");
    $rows = array();
    while ($row = $db->next_record()) {
      $productSale = new productSale();
      $productSale->read_db_record($db);
      $row["amounts"] = $productSale->get_amounts();
      $row["statusLabel"] = $statii[$row["status"]];
      $row["creatorLabel"] = $people[$row["productSaleCreatedUser"]]["name"];
      $row["productSaleLink"] = $productSale->get_link();
      $body.= productSale::get_list_body($row,$_FORM);
      $rows[] = $row;
    }

    if ($_FORM['return'] == 'array') {
      return $rows;
    }

    $header = productSale::get_list_header($_FORM);
    $footer = productSale::get_list_footer($_FORM);

    if ($body) {
      return $header.$body.$footer;
    } else {
      return "<table style=\"width:100%\"><tr><td style=\"text-align:center\"><b>No Product Sales Found</b></td></tr></table>";
    }
  }

  function get_list_header($_FORM=array()) {
    $ret[] = "<table class=\"list sortable\">";
    $ret[] = "<tr>";
    $ret[] = "  <th class=\"sorttable_numeric\">ID</th>";
    $ret[] = "  <th>Creator</th>";
    $ret[] = "  <th>Date</th>";
    $ret[] = "  <th>Client</th>";
    $ret[] = "  <th>Project</th>";
    $ret[] = "  <th>Status</th>";
    $ret[] = "  <th class=\"right\">Margin</th>";
    $ret[] = "</tr>";
    return implode("\n",$ret);
  }

  function get_list_body($sale,$_FORM=array()) {
    global $TPL;
    $TPL["_FORM"] = $_FORM;
    $TPL["sale"] = $sale;
    $TPL = array_merge($TPL,(array)$sale);
    return include_template(dirname(__FILE__)."/../templates/productSaleListR.tpl", true);
  }

  function get_list_footer($_FORM=array()) {
    $ret[] = "</table>";
    return implode("\n",$ret);
  }

  function get_link($row=array()) {
    global $TPL;
    if (is_object($this)) {
      return "<a href=\"".$TPL["url_alloc_productSale"]."productSaleID=".$this->get_id()."\">".$this->get_id()."</a>";
    } else {
      return "<a href=\"".$TPL["url_alloc_productSale"]."productSaleID=".$row["productSaleID"]."\">".$row["productSaleID"]."</a>";
    }
  }

  function get_statii() {
    return array("create"=>"Create", "edit"=>"Add Sale Items", "allocate" =>"Allocate", "admin"=>"Administrator", "finished"=>"Completed");
  }

  function get_all_parties($projectID="") {
    $db = new db_alloc;
    $interestedPartyOptions = array();

    if (!$projectID && is_object($this)) {
      $projectID = $this->get_value("projectID");
    }

    if ($projectID) {
      $interestedPartyOptions = project::get_all_parties($projectID);
    }

    $extra_interested_parties = config::get_config_item("defaultInterestedParties") or $extra_interested_parties=array();
    foreach ($extra_interested_parties as $name => $email) {
      $interestedPartyOptions[$email] = array("name"=>$name);
    }

    if (is_object($this)) {
      if ($this->get_value("personID")) {
        $p = new person;
        $p->set_id($this->get_value("personID"));
        $p->select();
        $p->get_value("emailAddress") and $interestedPartyOptions[$p->get_value("emailAddress")] = array("name"=>$p->get_name(), "selected"=>true, "personID"=>$this->get_value("personID"));
      }
      if ($this->get_value("productSaleCreatedUser")) {
        $p = new person;
        $p->set_id($this->get_value("productSaleCreatedUser"));
        $p->select();
        $p->get_value("emailAddress") and $interestedPartyOptions[$p->get_value("emailAddress")] = array("name"=>$p->get_name(), "selected"=>true, "personID"=>$this->get_value("productSaleCreatedUser"));
      }
      $this_id = $this->get_id();
    }
    // return an aggregation of the current proj/client parties + the existing interested parties
    $interestedPartyOptions = interestedParty::get_interested_parties("productSale",$this_id,$interestedPartyOptions);
    return $interestedPartyOptions;
  }


}


?>
