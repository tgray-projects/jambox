#!/bin/sh
killall -9 p4d
sleep 1
rm -rf /srv/p4/swarm /etc/perforce/p4dctl.conf.d/swarm.conf 
rm -rf /opt/perforce/swarm/data/queue /opt/perforce/swarm/data/config.php
