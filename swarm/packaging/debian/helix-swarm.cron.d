#
# Cron job to start Swarm workers every minute
#
* * * * * nobody [ -x /opt/perforce/swarm/p4-bin/scripts/swarm-cron.sh ] && /opt/perforce/swarm/p4-bin/scripts/swarm-cron.sh
