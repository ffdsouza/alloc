<?php

/*
 *
 * Copyright 2006, Alex Lance, Clancy Malcolm, Cybersource Pty. Ltd.
 * 
 * This file is part of allocPSA <info@cyber.com.au>.
 * 
 * allocPSA is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 * 
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * allocPSA; if not, write to the Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 */



// Read a template and return the mixed php/html, ready for processing
function get_template($filename, $use_function_object = false) {

  $template = implode("", (@file($filename)));
  if ((!$template) or(empty($template))) {
    echo "get_template() failure: [$filename]";
  }

  // Replace {:function_name param} with function("param"); - REMOVE THIS SOMETIME!
  if ($use_function_object) {
    $pattern = '/{(\w+)\s?([^}]*)}/i';
    $replace = '<?php $function_object->${1}${2}; ?>';
    $template = preg_replace($pattern,$replace,$template);
  }

  // Replace {$var_name} with echo stripslashes($TPL["var_name"]); 
  $pattern = '/{\$([\w|\d|_]+)}/i';
  $replace = '<?php echo stripslashes($TPL["${1}"]); ?>';
  $template = preg_replace($pattern,$replace,$template);

  // Replace {if hey}    with if (hey) { 
  // Replace {while hey} with while (hey) { 
  $pattern = '/{(if|while)+ ([^}]*)}/i';
  $replace = '<?php ${1} (${2}) TPL_START_BRACE ?>';
  $template = preg_replace($pattern,$replace,$template);

  
  // Replace {else with {TPL_END_BRACE else
  $pattern = '{else';
  $replace = '{TPL_END_BRACE else';
  $template = str_replace($pattern,$replace,$template);

  // Replace {TPL_END_BRACE else} with {TPL_END_BRACE else TPL_START_BRACE
  $pattern = '{TPL_END_BRACE else}';
  $replace = '{TPL_END_BRACE else TPL_START_BRACE}';
  $template = str_replace($pattern,$replace,$template);

  // Replace {TPL_END_BRACE else if hey} with {TPL_END_BRACE else if (hey) TPL_START_BRACE}
  $pattern = '/{TPL_END_BRACE else\s?if ([^}]+)}/i';
  $replace = '{TPL_END_BRACE else if (${1}) TPL_START_BRACE}';
  $template = preg_replace($pattern,$replace,$template);

  
  $sr = array("{/}"             => "<?php TPL_END_BRACE ?>"
             ,"{\n"             => "TPL_START_BRACE\n"      // Javascript brace workaround
             ,"\n}"             => "\nTPL_END_BRACE\n"      // Javascript brace workaround
             ,"{"               => "<?php "
             ,"}"               => " ?>"
             ,"TPL_END_BRACE"   => "}"
             ,"TPL_START_BRACE" => "{"
             ,".php&"           => ".php?"
            );

  foreach ($sr as $s => $r) {
    $searches[] = $s;
    $replaces[] = $r;
  }
  $template = str_replace($searches,$replaces,$template);


  return "?>$template<?php ";
}


// This is the publically callable function, used to include template files
function include_template($filename, $function_object = "") {
  global $TPL;
  echo "\n<!-- Start $filename -->\n";
  $template = get_template($filename, is_object($function_object));
  #echo "<pre>".htmlspecialchars($template)."</pre>"; 
  eval($template);
  echo "\n<!-- End $filename -->\n";
} 





?>
