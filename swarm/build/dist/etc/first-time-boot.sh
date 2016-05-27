#!/bin/bash

setpw()
{
    local user=$1

    local pwmsg="Please enter a new password for the '$user' system account."
    echo "$pwmsg"
    until passwd "$user"
    do
        echo Sorry, you must successfully set a new password to continue.
        echo
        echo "$pwmsg"
    done
}

query()
{
    local var="$1"
    local val

    shift # remove var name
    while :; do
        echo -n "$*: "
        read val
        case "$val" in
          '' ) echo "Please enter something!"
               echo ;;
          *  ) break ;;
        esac
    done

    echo
    eval "$var"="\$val"
}

clear
cat <<'__WELCOME__'

           ___          __                  ___                       
          | _ \___ _ _ / _|___ _ _ __ ___  / __|_ __ ____ _ _ _ _ __  
          |  _/ -_) '_|  _/ _ \ '_/ _/ -_) \__ \ V  V / _` | '_| '  \ 
          |_| \___|_| |_| \___/_| \__\___| |___/\_/\_/\__,_|_| |_|_|_|

Welcome to Perforce Swarm!

To get started, please answer the following questions to configure Swarm
on this virtual machine.

First, let's configure the OS system accounts.

__WELCOME__

setpw root
echo ""

setpw swarm
echo ""

cat << __HOSTNAME__
Second, let's set the hostname of this virtual machine. This name is what
users will connect to, so please ensure it is externally resolvable. When
Swarm sends email notifications, it will include links back to Swarm that
use this hostname.

__HOSTNAME__

while :; do
    query new_hostname "Hostname (e.g. swarm.yourdomain.com)"

    [[ "$new_hostname" =~ [^a-zA-Z0-9\.-] ]] &&
        echo "-error: hostname can only contain letters, numbers, '.' and '-'" && continue
    [[ "$new_hostname" =~ ^[0-9]*$ ]] &&
        echo "-error: hostname cannot be just numbers" && continue
    [[ "$new_hostname" =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]] &&
        echo "-error: hostname cannot be an IP address" && continue
    [[ ! "$new_hostname" =~ ^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$ ]] &&
        echo "-error: hostname is not valid" && continue

    break
done
hostname "$new_hostname"
echo "$new_hostname" > /etc/hostname
sed -i'' "/^127.0.0.1/s/\(.*\)/\1 $new_hostname/" /etc/hosts

# prep a Swarm config file
cat > /opt/perforce/swarm/data/config.php << __SWARM_CONFIG__
<?php
return array(
    'environment'  => array(
        'mode'     => 'production',
    ),
    'log' => array(
        'priority' => 3 // 3 for errors only; 7 for max logging
    ),
    'notifications' => array(
        'honor_p4_reviews' => false, // set to true to make Swarm act like review daemon
        //'opt_in_review_path' => '//depot/swarm'
    )
);
__SWARM_CONFIG__

swarmconfigscript="/opt/perforce/swarm/sbin/configure-swarm.sh"
if [ ! -r "$swarmconfigscript" ]; then
    echo "ERROR: cannot find Swarm configuration script [$swarmconfigscript]"
else
    cat << __CALL_CONFIG__
Now, we're going to call the Swarm configuration script.
For reference, it is located here:

    $swarmconfigscript

If you want to change your settings later, just log in as root and rerun
the configuration script.

__CALL_CONFIG__

    read -p "Press [Enter] to proceed."

    $swarmconfigscript -i -H "$new_hostname"

fi
# eof
