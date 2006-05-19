#!/bin/sh
#
#  Copyright 2006, Alex Lance, Clancy Malcolm, Cybersource Pty. Ltd.
#  
#  This file is part of allocPSA <info@cyber.com.au>.
#  
#  allocPSA is free software; you can redistribute it and/or modify it under the
#  terms of the GNU General Public License as published by the Free Software
#  Foundation; either version 2 of the License, or (at your option) any later
#  version.
#  
#  allocPSA is distributed in the hope that it will be useful, but WITHOUT ANY
#  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
#  A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
#  
#  You should have received a copy of the GNU General Public License along with
#  allocPSA; if not, write to the Free Software Foundation, Inc., 51 Franklin
#  St, Fifth Floor, Boston, MA 02110-1301 USA
# 
#
#  Script to setup database, website permissions, cronjobs and generate alloc.inc
#



# Directory of this file
DIR="${0%/*}/"

# Source functions
. ${DIR}functions.sh


e "Creating Executables"

if [ "0" != "$(id -u)" ]; then
  die "Please run this script as user root."
fi


# Make the alloc.inc file
e "Creating alloc.inc"
cat ${DIR}templates/alloc.inc.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_DB_NAME/${ALLOC_DB_NAME}/" \
| sed -e "s/CONFIG_VAR_ALLOC_DB_USER/${ALLOC_DB_USER}/" \
| sed -e "s/CONFIG_VAR_ALLOC_DB_PASS/${ALLOC_DB_PASS}/" \
| sed -e "s/CONFIG_VAR_ALLOC_DB_HOST/${ALLOC_DB_HOST}/" \
| sed -e "s/CONFIG_VAR_ALLOC_DOCS_DIR/${ALLOC_DOCS_DIR//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_LOG_DIR/${ALLOC_LOG_DIR//\//\/}/" \
> ${DIR}alloc.inc

if [ -f "${DIR}alloc.inc" ]; then 
  e_ok "Created alloc.inc"
  run "chmod 640 ${DIR}alloc.inc"                           
  run "chgrp ${ALLOC_WEB_USER} ${DIR}alloc.inc"             
else 
  e_failed "Could not create alloc.inc"; 
fi


e "Creating cron_allocBackup.sh"
cat ${DIR}templates/cron_allocBackup.sh.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_DB_NAME/${ALLOC_DB_NAME}/" \
| sed -e "s/CONFIG_VAR_ALLOC_BACKUP_DIR/${ALLOC_BACKUP_DIR//\//\/}/" \
| sed -e "s/CONFIG_VAR_ROOT_DB_PASS/${ROOT_DB_PASS//\//\/}/" \
> ${DIR}cron_allocBackup.sh

if [ -f "${DIR}cron_allocBackup.sh" ]; then 
  e_ok "Created cron_allocBackup.sh"
  run "chmod 700 ${DIR}cron_allocBackup.sh"            
  run "chown root ${DIR}cron_allocBackup.sh"                       
  run "mv ${DIR}cron_allocBackup.sh ${ALLOC_BACKUP_DIR}"
else 
  e_failed "Could not create cron_allocBackup.sh"; 
fi


e "Creating cron_checkRepeatExpenses.sh"
cat ${DIR}templates/cron_checkRepeatExpenses.sh.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_WEB_URL_PREFIX/${ALLOC_WEB_URL_PREFIX//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_LOG_DIR/${ALLOC_LOG_DIR//\//\/}/" \
> ${DIR}cron_checkRepeatExpenses.sh

if [ -f "${DIR}cron_checkRepeatExpenses.sh" ]; then 
  e_ok "Created cron_checkRepeatExpenses.sh"
  run "chmod 755 ${DIR}cron_checkRepeatExpenses.sh"     
else 
  e_failed "Could not create cron_checkRepeatExpenses.sh"; 
fi


e "Creating cron_sendEmail.sh"
cat ${DIR}templates/cron_sendEmail.sh.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_WEB_URL_PREFIX/${ALLOC_WEB_URL_PREFIX//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_LOG_DIR/${ALLOC_LOG_DIR//\//\/}/" \
> ${DIR}cron_sendEmail.sh

if [ -f "${DIR}cron_sendEmail.sh" ]; then 
  e_ok "Created cron_sendEmail.sh"
  run "chmod 755 ${DIR}cron_sendEmail.sh"     
else 
  e_failed "Could not create cron_sendEmail.sh"; 
fi


e "Creating cron_sendReminders.sh"
cat ${DIR}templates/cron_sendReminders.sh.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_WEB_URL_PREFIX/${ALLOC_WEB_URL_PREFIX//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_LOG_DIR/${ALLOC_LOG_DIR//\//\/}/" \
> ${DIR}cron_sendReminders.sh

if [ -f "${DIR}cron_sendReminders.sh" ]; then 
  e_ok "Created cron_sendReminders.sh"
  run "chmod 755 ${DIR}cron_sendReminders.sh"     
else 
  e_failed "Could not create cron_sendReminders.sh"; 
fi


e "Creating patch.sh"
cat ${DIR}templates/patch.sh.tpl \
| sed -e "s/CONFIG_VAR_ALLOC_LOG_DIR/${ALLOC_LOG_DIR//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_DB_NAME/${ALLOC_DB_NAME//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_BACKUP_DIR/${ALLOC_BACKUP_DIR//\//\/}/" \
| sed -e "s/CONFIG_VAR_ALLOC_PATCH_DIR/${ALLOC_PATCH_DIR//\//\/}/" \
| sed -e "s/CONFIG_VAR_ROOT_DB_PASS/${ROOT_DB_PASS//\//\/}/" \
> ${DIR}patch.sh

if [ -f "${DIR}patch.sh" ]; then 
  e_ok "Created patch.sh"
  run "chmod 700 ${DIR}patch.sh"                          
  run "chown root ${DIR}patch.sh"                       
else 
  e_failed "Could not create patch.sh"; 
fi



