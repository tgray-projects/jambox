#!/usr/bin/env bash
#
# Perforce Workshop Protections Trigger Script
#
# @copyright   2015 Perforce Software. All rights reserved.
# @version     <release>/<patch>
#
# This script is meant to be called from a Perforce trigger.
# It should be placed on the Perforce Server machine.
# See usage information below for more details.

# Ensures that no two uers can modify the protects table at the same time.
# Priority is given to the swarm user.
# Out of date changes are rejected.

current_user=$1
form_action=$2
swarm_user='swarm_admin'
data_path='/tmp/'
lockfile_name="protects.lock"
lockfile=$data_path$lockfile_name

error_message="The protects table is currently being updated.  Please try again shortly."

if [ "$form_action" = "in" ]
then
    if [ -f $lockfile ]
    then
        lockuser=$(<$lockfile)

        # protects is currently being updated by swarm, we stop
        if [ "$lockuser" = "$swarm_user" ]
        then
            if [ "$current_user" = "$swarm_user" ]
            then
                echo 'busy'
                exit 1;
            fi
            # at this point, the protects table is being updated by swarm and we are not swarm
            echo $error_message
            exit 1
        fi

        # protects is updated by regular user
        if [ "$current_user" != "$swarm_user" ]
        then
            echo $error_message
            exit 1;
        fi

        # at this point, the protects table is being modified by a non-swarm user and we are the swarm user
        # so we have priority
        echo $swarm_user > $lockfile
        exit 0;
    fi

    # there is no lockfile; create one with our userid
    echo $current_user > $lockfile;
    exit 0;
fi

if [ "$form_action" = "save" ]
then
    # no lock file?  proceed
    if [ ! -f $lockfile ]
    then
        exit 0;
    fi

    lockuser=$(<$lockfile)
    if [ "$lockuser" = "$current_user" ]
    then
        rm $lockfile
        exit 0;
    fi
    echo $error_message
    exit 1
fi

echo "Unrecognized condition."
exit 1;