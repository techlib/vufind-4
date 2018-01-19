#!/bin/bash

#DM by MJ @NTK 2014-12-08, update 01/2018

export VUFIND_HOME=/var/www/vufind.4
export VUFIND_LOCAL_DIR=/var/www/vufind.4/local
#export JAVA_HOME=/usr/java/latest/jre/
#export JAVA_HOME=/usr/lib/jvm/java-1.6.0-openjdk-1.6.0.0/jre

DATE=$(date +%F)
OAI_SET=katalog_ntk-update
LOG_FILE=$VUFIND_LOCAL_DIR/harvest/$OAI_SET/log/cron/$DATE.log
#reccomended by vufind - automation page
export JETTY_CONSOLE=/dev/null

# echo "cesty: $VUFIND_HOME $JAVA_HOME"
date +%X-%x >> $LOG_FILE
echo "CRONJOB STARTED" >>$LOG_FILE
# Makes sure we exit if flock fails.
set -e

(
  # Wait for lock on /var/lock/.myscript.exclusivelock (fd 200) for 10 seconds
    flock -x -w 10 200

      # Do stuff
         
      #create log file
        date +%X-%x >> $LOG_FILE
	#  echo -e "\n" >> $LOG_FILE

	#next scripts can fail but we will continue
	  set +e


	  #restart !!!
	    echo "v-v-v-v-v-v-v-v-v-v-v-v-v" >> $LOG_FILE
	      date +%X-%x >> $LOG_FILE
            echo -e "** RESTART **\n" >> $LOG_FILE
	     $VUFIND_HOME/solr.sh restart & 2>&1 >>$LOG_FILE
	     ps aux | grep java >> $LOG_FILE
	     sleep 1m
          #harvest
             date +%X-%x >> $LOG_FILE
            echo -e "** harvest **\n" >> $LOG_FILE
	    cd /var/www/vufind.4/harvest
	     php harvest_oai.php >> $LOG_FILE
	  #import
	     date +%X-%x >> $LOG_FILE
	    echo -e "** import **\n" >> $LOG_FILE
	    $VUFIND_HOME/harvest/batch-import-marc.sh katalog_ntk-update  2>&1 >>$LOG_FILE
          #delete
	     date +%X-%x >> $LOG_FILE
	    echo -e "** delete **\n" >> $LOG_FILE
	    $VUFIND_HOME/harvest/batch-delete.sh katalog_ntk-update/  2>&1 >> $LOG_FILE
#optimize 
#  date +%X-%x >> $LOG_FILE
  echo -e "** optimize **\n" >> $LOG_FILE
  #  cd $VUFIND_HOME
  php $VUFIND_HOME/util/optimize.php
  #alphabetical
  date +%X-%x >> $LOG_FILE
  echo -e "** alphabetical browse **\n" >> $LOG_FILE
  $VUFIND_HOME/index-alphabetic-browse.sh
  date +%X-%x >> $LOG_FILE
  echo -e "**** DONE! ****\n" >> $LOG_FILE
  echo "^-^-^-^-^-^-^-^-^-^-^-^-^" >> $LOG_FILE

#  set -e
) 200>$VUFIND_HOME/vufindcron.lock

		#nepokazi se to tu???
		rm $VUFIND_HOME/vufindcron.lock

		#stary cron job :
		# VUFIND_HOME=/var/www/vufind; JAVA_HOME=/usr/java/latest/jre/; cd /var/www/vufind/harvest; echo "cesty: $VUFIND_HOME $JAVA_HOME" > /home/vufind/crontab.log; VUFIND_HOME=/var/www/vufind php /var/www/vufind/harvest/harvest_oai.php katalog_ntk-update 2>&1 >> /home/vufind/crontab.log ;  VUFIND_HOME=/var/www/vufind /var/www/vufind/harvest/batch-import-marc.sh katalog_ntk-update  2>&1 >> /home/vufind/crontab.log;  VUFIND_HOME=/var/www/vufind /var/www/vufind/harvest/batch-delete.sh katalog_ntk-update/  2>&1 >> /home/vufind/crontab.log

		                          
