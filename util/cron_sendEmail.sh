#!/bin/sh
#
# This script will trigger allocs daily task email summaries.
#

# path to cron and log files
PREFIX=`dirname $0`"/../logs/"

# wget the php script
wget -q -O ${PREFIX}sendEmail.log -P ${PREFIX} http://alloc/person/sendEmail.php

