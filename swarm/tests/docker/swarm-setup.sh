#!/bin/bash

# Getting IP Address of docker image
ADDRESS=$(/sbin/ifconfig eth0 | grep "inet addr:" | cut -d: -f2 | awk '{print $1}')
PORT=":1666"
export P4PORT=$ADDRESS$PORT

# Starting Perforce Server
mkdir /server
p4d -d -p "$ADDRESS$PORT" -r /server -J /server/journal -L /server/logfile

SUPERUSER="swarm_super"
SUPERPASS="super_pass"
# Creating swarm super user
p4 user -f -i <<USER
User: $SUPERUSER
Email: $SUPERUSER@perforce.com
FullName: Swarm Super
Password: $SUPERPASS
USER

ADMINUSER="swarm_admin"
ADMINPASS="admin_pass"
# Creating swarm admin user
p4 user -f -i <<USER
User: $ADMINUSER
Email: $ADMINUSER@perforce.com
FullName: Swarm Admin
Password: $ADMINPASS
USER

PLATFORM=`grep DISTRIB_ID /etc/*-release | awk -F '=' '{print $2}'`
if [ $PLATFORM = "Ubuntu" ]
then
    APACHE_USER="www-data:www-data"
else # If the PLATFORM is not Ubuntu, then assume it is Centos.
    APACHE_USER="apache:apache"
fi

# Setting protect permissions for admin user
p4 -u $SUPERUSER -P $SUPERPASS protect -o | \
    sed -e "0,/^\tsuper/s##\tadmin user $ADMINUSER * //...\n\tsuper#" | \
	p4 -u $SUPERUSER -P $SUPERPASS protect -i

# Disabling default vhost
a2dissite 000-default
	
# Running configure-swarm.sh
HOSTNAME=$(hostname)
/opt/perforce/swarm/sbin/configure-swarm.sh -p "$P4PORT" -u "$ADMINUSER" -w "$ADMINPASS" -H "$HOSTNAME" -e smtp.perforce.com

# Installing triggers
{ (p4 -u "$SUPERUSER" -P "$SUPERPASS" triggers -o; /opt/perforce/swarm/p4-bin/scripts/swarm-trigger.sh -o ) | p4 -u "$SUPERUSER" -P "$SUPERPASS" triggers -i; }

# Creating token
mkdir -p /opt/perforce/swarm/data/queue/tokens
mkdir /opt/perforce/swarm/data/queue/workers
UUID=$(uuidgen)
touch /opt/perforce/swarm/data/queue/tokens/"$UUID"
chown "$APACHE_USER" /opt/perforce/swarm/data/queue/tokens/"$UUID" /opt/perforce/swarm/data/queue/workers
chmod 640 /opt/perforce/swarm/data/queue/tokens/"$UUID"

# Configuring trigger script
sed -i -e "s/my-swarm-host/$HOSTNAME/g" /opt/perforce/swarm/p4-bin/scripts/swarm-trigger.sh
sed -i -e "s/MY-UUID-STYLE-TOKEN/$UUID/g" /opt/perforce/swarm/p4-bin/scripts/swarm-trigger.sh

