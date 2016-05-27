#!/bin/bash

# Check that Swarm was installed correctly
echo "Checking swarm install..."
if [ -d "/opt/perforce/swarm" ]
then
    echo "Swarm installed correctly"
    cat "/opt/perforce/swarm/Version"
else
    echo "Swarm not installed"
    exit 1
fi

PLATFORM=`grep DISTRIB_ID /etc/*-release | awk -F '=' '{print $2}'`
if [[ "$PLATFORM" == 'Ubuntu' ]]; then
    CRONLOG="/var/log/syslog"
    CONFIG_FILES="\
        /etc/apache2/sites-available/perforce-swarm-site.conf \
        /etc/cron.d/perforce-swarm \
        /etc/php5/apache2/conf.d/perforce.ini \
        /opt/perforce/swarm/data/config.php"
    ERROR_LOGS="\
        /var/log/apache2/swarm.error_log \
        /var/log/apache2/swarm.access_log \
        /opt/perforce/swarm/data/log \
        /var/log/syslog"

else # If the PLATFORM is not Ubuntu, then assume it is Centos.
    CRONLOG="/var/log/cron"
    ERROR_LOGS="\
        /var/log/httpd/swarm.error_log \
        /var/log/httpd/swarm.access_log \
        /opt/perforce/swarm/data/log \
        /var/log/messages"
    CONFIG_FILES="\
        /etc/httpd/conf.d/perforce-swarm-site.conf \
        /etc/cron.d/perforce-swarm \
        /opt/perforce/swarm/data/config.php \
        /etc/php.d/perforce.ini"
fi

# Authenticate and check that workers are running
SUPERUSER="swarm_super"
SUPERPASS="super_pass"
HOSTNAME="$(hostname)"

echo "Authenticating and verifying workers exist..."
for i in `seq 1 61`; do
    [ -f "/opt/perforce/swarm/data/queue/workers/1" ] && break
    sleep 1
done
if [ ! -f "/opt/perforce/swarm/data/queue/workers/1" ]; then
    echo "Error - cron did not start any workers."
    exit 1
fi

echo "Validating a curl against swarm..."
QUEUE="$(curl -s -u "$SUPERUSER:$SUPERPASS" "http://$HOSTNAME/queue/status")"
if egrep -q "(worker\":0)" "$QUEUE"; then
    echo "Curl on status failed."
    exit 1
fi

# Check that the cron job is running correctly
echo "Checking cron job started successfully..."
if ! ( grep -q CRON "$CRONLOG" ); then #needs !
    echo "Cron job not running"
    exit 1
fi

# Assert configured files exist
for file in $CONFIG_FILES
do
    if [ ! -f "$file" ]; then
        echo "File: $file does not exist"
        exit 1
    fi
done

# Check logs for errors
echo "Checking logs for errors..."
for file in $ERROR_LOGS
do
    if egrep -q "(Fatal|error)" "$file"; then
        echo "Error in $file"
        exit 1
    fi
done

echo "Swarm Package sanity test successfully completed"
