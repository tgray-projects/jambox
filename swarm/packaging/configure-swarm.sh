#!/bin/bash
ME="configure-swarm.sh"
#-------------------------------------------------------------------------------
# Configuration script for Perforce Swarm 
# Copyright 2013-2014 Perforce Software, Inc. All rights reserved.
#-------------------------------------------------------------------------------

usage()
{
    cat << __USAGE__
Usage:
    $ME -i [-f]
    $ME -i [-f] [-p p4port] [-u swarm-user] [-w swarm-passwd] \\
    ${ME//?/ } -e email-host [-H swarm-host]
    $ME [-f] -p p4port -u swarm-user -w swarm-passwd \\
    ${ME//?/ } -e email-host [-H swarm-host]
    $ME -i [-f] -c
    $ME [-i] -p p4port -u swarm-user -w swarm-passwd \\
    ${ME//?/ } -e email-host [-H swarm-host] \\
    ${ME//?/ } -c -U super-user -W super-passwd

    -i|--interactive            -Prompt user for arguments not passed
    -p|--p4port <P4PORT>        -Perforce Server address
                                 (defaults to P4PORT from environment)
    -u|--swarm-user <username>  -Perforce user name for Swarm
    -w|--swarm-passwd <passwd>  -Swarm user password or login ticket
    -H|--swarm-host <hostname>  -Hostname for this Swarm server
                                 (defaults to [$DEFAULT_SWARM_HOST])
    -e|--email-host <hostname>  -Mail relay host

    -f|--force                  -Do not check p4port nor swarm creds
                                 (not applicable if creating creds)

    -c|--create                 -Create the Swarm user
    -U|--super-user <username>  -Perforce super-user login name
    -W|--super-passwd <passwd>  -Perforce super-user password or login ticket

    -h|--help                   -Display usage, plus examples and notes

__USAGE__
    [[ "$1" == "exit" ]] && exit 1
}
usage_error()
{
    [[ -n "$1" ]] && echo "$ME: $*"
    usage exit
}
usage_help()
{
    usage
    cat << __HELP__
Interactive Examples:
    
    Prompt user for information needed to configure Swarm, including the p4port,
    Swarm user & password, email host and Swarm host; entered values will be
    validated:
    $ $ME -i

    Like above, but skip any validation checks. This is useful if the Perforce
    server is not currently available, but you know the values are correct:
    $ $ME -i -f

    Specify some of arguments, but prompt for those not specified or for those
    that might fail a validation check; this lets you correct a bad value given.
    This is also useful if you want manully enter some of the arguments (such as
    a password):
    $ $ME -i -p p4host:1666 -u swarm

    Like the first example, but prompt for super user credentials to create the
    Swarm user using values obtained interactively.
    $ $ME -i -c

    Like above, but specify as much on the command line, with the exception of
    passwords:
    $ $ME -i -p p4port -u swarm -c super

Non-Interactive Examples:

    Configure using existing swarm/swarmpw credentials on p4server:1666, without
    prompting user for any values. Note that in this case, the Swarm user
    credentials must be valid, or configuration will abort:
    $ $ME -p p4host:1666 -u swarm -w swarmpw -e mx.example.com

    Like above, but also set Swarm host name; note that we do not verify if
    the host specified will actually reach this host:
    $ $ME -p p4host:1666 -u swarm -w swarmpw -e mx.example.com -H swarm.company.com

    Like above, but avoid any validity checks:
    $ $ME -p p4host:1666 -u swarm -w swarmpw -e mx.example.com -H swarm.company.com -f

    Configure, passing Perforce super user credentials; note that if the Swarm
    user already exists, but the password is incorrect, configuration will
    abort:
    $ $ME -p p4host:1666 -u swarm -w swarmpw -e mx.example.com -c -U super -W superpw

    Like above, but skip any validity checks (beyond the super user credentials
    needing to be valid); this means that if the Swarm user already exists, but
    the password is different, the password will be reset:
    $ $ME -p p4host:1666 -u swarm -w swarmpw -e mx.example.com -c -U super -W superpw -f

Notes:

* This script needs to be run as root.

* If a Perforce password specified looks like a login ticket (32 hexdecimal
  characters), it will be assumed to be as such.
  
* If using a login ticket instead of a password for the Swarm user, please
  ensure the user belongs to a group with an appropriately long timeout value.

__HELP__
    exit 1
}

#-------------------------------------------------------------------------------
# Global variables
#-------------------------------------------------------------------------------

# Perforce installation root
P4_INSTALL_ROOT="/opt/perforce"

# Perforce configuration directory
P4_CONFIG_DIR="${P4_INSTALL_ROOT}/etc"

# Where we install Swarm
SWARM_DIR="${P4_INSTALL_ROOT}/swarm"

# Where we log the output of this script
LOG="${SWARM_DIR}/data/${ME%.sh}.log"

# Swarm config file
SWARM_CONFIG="${SWARM_DIR}/data/config.php"

# Default Swarm username
DEFAULT_SWARM_USER="swarm"

# Default Swarm host
DEFAULT_SWARM_HOST="$(hostname)"

# Location of P4 binary
P4_BINDIR="${P4_INSTALL_ROOT}/usr/bin"

# PHP configuration directory
PHP_INI_DIR_RHEL="/etc/php.d"
PHP_INI_DIR_UBUNTU="/etc/php5/apache2/conf.d"

# Apache user/group info
APACHE_USER_RHEL="apache"
APACHE_GROUP_RHEL="apache"
APACHE_USER_UBUNTU="www-data"
APACHE_GROUP_UBUNTU="www-data"

# Apache log directories
APACHE_LOG_DIR_RHEL="/var/log/httpd"
APACHE_LOG_DIR_UBUNTU="/var/log/apache2"

# Virtual host config file
VHOST_CONFIG_RHEL="/etc/httpd/conf.d/perforce-swarm-site.conf"
VHOST_CONFIG_UBUNTU="/etc/apache2/sites-available/perforce-swarm-site.conf"

# Cron configuration file
CRON_CONFIG="${P4_CONFIG_DIR}/swarm-cron-hosts.conf"

# Holder of any warnings to report during configuration
WARNINGS=

#-------------------------------------------------------------------------------
# Functions
#-------------------------------------------------------------------------------

# Abort
die()
{
    echo -e "\n$ME: error: $@"
    exit 1
}

# Display warning and save it for summary at the end
warn()
{
    local msg="$1"
    [[ -z "$msg" ]] && die "no message passed to warn()"

    echo -e "\n>>>> WARNING <<<<\n$msg\n"
    WARNINGS="${WARNINGS}
${msg}
"
    
    return 0
}

# Prompt the user for information by showing a prompt string
# If the prompt is for a password, disable echo on the terminal.
# Optionally call validation function to check if the response is OK.
#
# promptfor <VAR> <prompt> [<ispassword>] [<defaultvalue>] [<validationfunc>]
promptfor()
{
    local secure=false
    local default_value=""
    local check_func=true

    local var="$1"
    local prompt="$2"
    [[ -n "$3" ]] && secure="$3"
    [[ -n "$4" ]] && default_value="$4"
    [[ -n "$5" ]] && check_func="$5"

    [[ -n "$default_value" ]] && prompt="$prompt [$default_value]"
    $secure && prompt="$prompt (typing hidden)"

    while true; do
        local resp=""
        $secure && stty -echo echonl

        read -p "$prompt: " resp
        stty echo -echonl

        if [[ -z "$resp" && -n "$default_value" ]]; then
            resp="$default_value"
            echo "-using default value [$resp]"
        else
            ! $secure && echo "-response: [$resp]"
        fi

        if $check_func "$resp"; then
            eval "$var='$resp'"
            break;
        else
            echo "-please try again"
        fi
    done
    echo ""
}
validate_nonempty()
{
    [[ ! -n "$1" ]] && echo "-empty response given" && return 1
    return 0
}
validate_ticket()
{
    [[ ! -n "$1" ]] && echo "-empty response given" && return 1
    [[ ! "$1" =~ ^[0-9A-F]{32}$ ]] &&
        echo "-response does not look like a ticket value (32 hex characters)" &&
        return 1
    return 0
}
validate_noticket()
{
    [[ ! -n "$1" ]] && echo "-empty response given" && return 1
    [[ "$1" =~ ^[0-9A-F]{32}$ ]] &&
        echo "-response looks like a ticket value" &&
        return 1
    return 0
}
validate_username()
{
    local badchars='[ @#]'
    local allnums='^[0-9]*$'
    [[ -z "$1" ]] && echo "-empty response given" && return 1
    [[ "$1" =~ ^- ]] && echo "-username cannot start with '-'" && return 1
    [[ "$1" =~ \.\.\. ]] && echo "-username cannot contain '...'" && return 1
    [[ "$1" =~ $badchars ]] && echo "-username cannot contain '@', '#' or space" && return 1
    [[ "$1" =~ $allnums ]] && echo "-username cannot contain only numbers" && return 1
    return 0
}

# Function to prompt the user for a new password and confirm that password.
# Retries until the passwords match.
new_password()
{
    local p1
    local p2
    local var="$1"
    local label="$2"
    local func="$3"

    while [[ -z "$p1" || "$p1" != "$p2" ]]; do
        promptfor p1 "Enter $label" true "" "$func"
        promptfor p2 "Confirm $label" true ""  # No validation needed

        if [[ "$p1" != "$p2" ]]; then
            echo "Passwords do not match"
        fi
    done

    eval "$var='$p1'"
}

# Determine our supported OSes and distributions
set_os_distribution()
{
    # Determine distribution
    if [[ -e "/etc/redhat-release" ]]; then
        DISTRO="RHEL"
    elif [[ -e "/etc/debian_version" ]]; then
        DISTRO="UBUNTU"
    else
        die "cannot determine distribution for this OS ($(uname -s)); abort"
    fi
}

# Ensure we can connect to defined P4PORT
# Detect if we need to trust SSL Perforce server
# Detect if we need to set a charset
# Sets P4 for use elsewhere
check_p4port()
{
    [[ -z "$P4PORT" ]] && echo "No P4PORT specified" && return 1
    local info

    echo "Checking P4PORT [$P4PORT]..."

    # Initialize p4 command with full path to binary
    P4="$P4_BINDIR/p4"
    if [[ ! -x "$P4" ]]; then
        echo "-cannot find [$P4]; checking if 'p4' in path..."
        if which p4 > /dev/null; then
            P4="$(which p4)"
            echo "-found [$P4]"
        else
            die "could not find a 'p4' binary; abort"
        fi
    fi

    # Add the p4port to the command string
    P4="$P4 -p $P4PORT"

    # Establish SSL trust first if required.
    if [[ "${P4PORT:0:3}" == "ssl" ]]; then
        echo "-establishing trust to [$P4PORT]..."
        $P4 trust -f -y || return $?
    fi

    # Set character-set explicitly if talking to a unicode server
    if $P4 -ztag info | grep -q '^\.\.\. unicode enabled'; then
        local p4charset="${P4CHARSET:-utf8}"
        echo "-Unicode Perforce Server detected; setting charset to [$p4charset]..."
        P4="$P4 -C $p4charset"
    fi

    # Explicitly set the client if we detect an illegal client name
    if $P4 -ztag info | grep -q '^\.\.\. clientName \*illegal\*'; then
        local p4client="$(hostname)"
        p4client="${p4client%%.*}-client"
        echo "-setting client name to [$p4client]..."
        P4="$P4 -c $p4client"
    fi

    echo "-P4 command line to use: [$P4]"

    $FORCE && ! $CREATE && [[ -n "$P4PORT" ]] && return 0

    echo "Attempting connection to [$P4PORT]..."
    info="$($P4 info 2> /dev/null | egrep "^Server(ID| (address|version|license))" | sort)"
    if [[ $? -ne 0 || -z "$info" ]]; then
        echo "-unable to connect"
        return 1
    else
        echo "-connection successful:"
        echo "$info" | sed -e "s,^,  ,g"
    fi
    
    return 0
}

# Simple check to see if supplied user exists or not
check_p4_user()
{
    [[ -z "$1" ]] && echo "No user specified" && return 1
    local user="$1"
    local p4user

    [[ -n "$SUPER_USER" ]] &&
        p4user="$SUPER_USER" ||
        p4user="$user"

    echo "-checking if user [$user] exists in [$P4PORT]..."
    if $P4 -u "$p4user" users | awk '{print $1}' | grep -qx "$user"; then
        echo "-user exists"
        return 0
    else
        echo "-user does not exist"
        return 1
    fi
}

# Obtains a ticket by using 'login -p' with the password
# Outputs ticket
get_ticket_from_login()
{
    local user="$1"
    local passwd="$2"
    local ticket

    ticket="$(echo "$passwd" | $P4 -u "$user" login -p | egrep "^[0-9A-Z]{32}$" | head -n1 )"
    if [[ $? -ne 0 ]] || ! validate_ticket "${ticket}"; then
        return 1
    fi

    echo "$ticket"
    return 0
}

# Logs in using the ticket to verify it works
test_p4_user_ticket()
{
    local user="$1"
    local ticket="$2"

    echo "Checking user [$user]'s ticket against [$P4PORT]..."
    # Test the ticket by looking at the user's form
    if $P4 -ztag -u "$user" -P "$ticket" user -o | grep -qx "\.\.\. User $user"; then
        echo "-login ticket is good"
        return 0
    else
        echo "-unable to login with ticket"
        return 1
    fi
}

# Manages process to get a ticket
# Use password if it looks like a ticket
# Retrieve ticket
# Test the ticket
get_p4_login_ticket()
{
    local user="$1"
    local passwd="$2"
    local ticket_var="$3"
    local ticket

    echo "Obtaining Perforce login ticket for [$user] in [$P4PORT]..."
    [[ -z "$passwd" ]] && echo "-no password specified" && return 1

    # Check if password specified is a login ticket
    if [[ "$passwd" =~ ^[0-9A-Z]{32}$ ]]; then
        echo "-password specified looks like a login ticket"
        ticket="$passwd"
    else
        ticket="$(get_ticket_from_login "$user" "$passwd")"
        if [[ $? -ne 0 ]]; then
            echo "-unable to obtain ticket"
            return 1
        else
            echo "-login ticket obtained"
        fi
    fi

    # Now test the ticket
    if ! test_p4_user_ticket "$user" "$ticket"; then
        # Pretty serious
        die "obtained ticket, but ticket check failed; abort"
    fi

    eval "$ticket_var=$ticket"
    return 0
}

check_p4_user_login_timeout()
{
    $FORCE && echo "-force flag set; skipping login timeout check" && return 0

    local user="$1"
    local ticket="$2"
    local ticket_expiry

    echo "Checking login timeout for [$user]..."
    ticket_expiry="$($P4 -u "$user" -P "$ticket" login -s | sed -re "s,.*expires in ([0-9]+) hours.*,\1,")"
    if [[ $? -ne 0 || -z "$ticket_expiry" ]]; then
        die "trouble obtaining ticket expiry for [$user]; abort"
    else
        echo "-ticket will expire in [$ticket_expiry] hours"
    fi
    
    # Check if expiry is less than 365 days
    if [[ "$ticket_expiry" -lt $((24 * 365)) ]]; then
        warn "The ticket for user [$user] will expire in [$ticket_expiry] hours, less than 365 days.
We recommend you add this user to a group with a longer timeout."
    fi

    return 0
}

# Simple check to make sure user has at least a certain Perforce access level
# Only admin and super are supported now
check_p4_min_access()
{
    local user="$1"
    local ticket="$2"
    local min_access="$3"
    local access
    local p4user
    local user_to_check

    # Use super credentials if we have them
    if [[ -n "$SUPER_USER" ]];
    then
        p4user="$SUPER_USER"
        ticket="$SUPER_TICKET"
        user_to_check="-u $user"
    else
        p4user="$user"
    fi

    echo "Checking user [$user] has at least access level [$min_access]..."
    access="$($P4 -u "$p4user" -P "$ticket" protects -m $user_to_check)"
    if [[ $? -ne 0 || -z "$access" ]]; then
        echo "-problem obtaining access"
        return 1
    else
        echo "-user has maximum access level [$access]"
    fi

    # If max access is super, good
    # If max access is what min access is, good
    # If max access is admin, and min access is not super, good
    if [[ "$access" == "super" || "$access" = "$min_access" ||
        ( "$access" == "admin" && "$min_access" != "super" ) ]]; then
        echo "-user meets minimum access level [$min_access]"
        return 0
    fi

    # Only support checking against admin and super for now
    echo "-user access level [$access] is not at least [$min_access]"
    return 1
}

# Output the value of a p4 counter value
get_p4_counter_value()
{
    local counter="$1"

    value="$($P4 -ztag -u "$SWARM_USER" -P "$SWARM_TICKET" counter "$counter" |
        sed -n "/^\.\.\. value /s///p")"
    if [[ $? -ne 0 || -z "$value" ]]; then
        return 1
    fi

    echo "$value"
    return 0
}

# Output the value of a p4 configuration value; requires super
get_p4_config_value()
{
    local super="$1"
    local ticket="$2"
    local variable="$3"
    local value

    value="$($P4 -ztag -u "$super" -P "$ticket" configure show "$variable" |
        sed -n "/^\.\.\. Value /s///p")"
    if [[ $? -ne 0 || -z "$value" ]]; then
        return 1
    fi

    echo "$value"
    return 0
}

# Set any configuration necessary by the super user
set_super_config()
{
    local super="$1"
    local ticket="$2"
    local keys_hide

    echo "Checking configuration value of dm.keys.hide..."
    keys_hide="$(get_p4_config_value "$super" "$ticket" "dm.keys.hide")"
    if [[ $? -ne 0 || -z "$keys_hide" ]]; then
        warn "Unable to obtain configuration value for dm.keys.hide."
        return 1
    else
        echo "-value is [$keys_hide]"
    fi

    if [[ $keys_hide -lt 2 ]]; then
        echo "-setting dm.keys.hide=2..."
        $P4 -u "$super" -P "$ticket" configure set dm.keys.hide=2
        if [[ $? -ne 0 ]]; then
            warn "Unable to set dm.keys.hide=2.
Without this, users will be able to set keys, potentially corrupting Swarm."
            return 1
        fi

        echo "-value set"
    else
        echo "-value is good"
    fi

    return 0
}

# Check super user credentials passed in from user
# Only necessary when creating Swarm user
check_super_creds()
{
    echo "Checking super user credentials..."
    check_p4_user "$SUPER_USER" || return $?
    get_p4_login_ticket "$SUPER_USER" "$SUPER_PASSWD" "SUPER_TICKET" || return $?
    check_p4_min_access "$SUPER_USER" "$SUPER_TICKET" "super" || return $?
}

# Check user creation constraints
check_p4_user_creation()
{
    local super="$1"
    local super_ticket="$2"
    local trigger_types=""
    local auth_check_sso=false
    local auth_check=false
    local auth_set=false

    [[ -z "$super" ]] && echo "-no super user specified" && return 1
    [[ -z "$super_ticket" ]] && echo "-no super user ticket specified" && return 1

    local p4cmd="$P4 -u $super -P $super_ticket"

    echo "Checking Perforce user account creation constraints..."

    echo "-checking for auth triggers..."
    trigger_types="$($p4cmd triggers -o | grep "^[^#]" | awk '{print $2}')"
    if [[ $? -ne 0 ]]; then
        die "trouble obtaining trigger types; abort"
    fi
    echo "$trigger_types" | grep -q "auth-check-sso" && auth_check_sso=true
    echo "$trigger_types" | grep -q "auth-check" && auth_check=true
    echo "$trigger_types" | grep -q "auth-set" && auth_set=true

    echo "-auth-check-sso trigger? [$($auth_check_sso && echo yes || echo no)]"
    echo "-auth-check trigger?     [$($auth_check && echo yes || echo no)]"
    echo "-auth-set trigger?       [$($auth_set && echo yes || echo no)]"

    # Catch if auth-check-sso is in place
    if $auth_check_sso && ! $auth_check; then
        warn "\
Swarm is incompatible with a Perforce Server that uses an auth-check-sso trigger.
You can add an auth-check trigger which can act as a fall-back."
    fi

    CAN_SET_P4_PASSWD=true
    if $auth_check; then
        warn "\
Your Perforce server at [$P4PORT] has an 'auth-check' trigger.
Please ensure the Swarm user [$SWARM_USER] exists in your
external authentication system."

        ! $auth_set && CAN_SET_P4_PASSWD=false
    fi

    return 0
}

# Check for the Swarm user
# Complain if it already exists if we're going to create it
check_swarm_user()
{
    $FORCE && [[ -n "$SWARM_USER" ]] && echo "-force flag set; skipping" && return 0
    [[ -z "$SWARM_USER" ]] && echo "No Swarm user specified" && return 1

    if check_p4_user "$SWARM_USER"; then
        # if user exists, fail if we're creating, else succeed
        if $CREATE; then
            echo "-Swarm user cannot already exist if creating credentials; drop -c flag or try -f flag?"
            return 1
        else
            return 0
        fi
    else
        # if user does not exist, succeed if we're creating, else fail
        $CREATE && return 0 || return 1
    fi
}

# The Perforce stuff to create a Swarm user
# Creates the user itself
# Sets the password (and then gets the ticket)
create_p4_swarm_user()
{
    [[ -z "$SUPER_USER" ]] && echo "-no super user defined" && return 1
    [[ -z "$SUPER_TICKET" ]] && echo "-no super user ticket defined" && return 1
    [[ -z "$SWARM_PASSWD" ]] && echo "-no Swarm password set" && return 1

    local p4cmd="$P4 -u $SUPER_USER -P $SUPER_TICKET"

    echo "-creating Perforce user [$SWARM_USER]..."
	$p4cmd user -o "$SWARM_USER" | sed -e "s/^FullName:.*/FullName: Swarm Admin/" | $p4cmd user -i -f
    if [[ $? -ne 0 ]] || ! check_p4_user "$SWARM_USER"; then
        die "trouble creating user; abort"
        return 1
    else
        echo "-user created"
    fi

    if $CAN_SET_P4_PASSWD; then
        echo "-setting password for Perforce user [$SWARM_USER]..."
        echo -e "$SWARM_PASSWD\n$SWARM_PASSWD" | $p4cmd password "$SWARM_USER"
        if [[ $? -ne 0 ]]; then
            echo -e "\n-trouble setting password (any error above?); cleaning up to try again..."
            $p4cmd user -d -f "$SWARM_USER"
            return 1
        else
            echo "-password set"
        fi
    else
        echo "-skipping setting password due to auth-check trigger"
    fi

    if ! get_p4_login_ticket "$SWARM_USER" "$SWARM_PASSWD" "SWARM_TICKET"; then
        # Maybe it's an access problem?
        if ! check_p4_min_access "$SWARM_USER" "$SWARM_TICKET" "list"; then
            echo -e "\n-user [$SWARM_USER] has no Perforce access; update protections table to grant access and try again?"
        else
            echo -e "\n-trouble obtaining user ticket (any error above?)"
        fi
        echo "-cleaning up..."
        $p4cmd user -d -f "$SWARM_USER"
        die "could not obtain user ticket; abort"
    fi

    return 0
}

# Check Swarm credentials passed in from user
check_swarm_creds()
{
    echo "Checking Swarm user credentials..."
    check_swarm_user || return $?

    if $CREATE; then
        echo "Creating Swarm user [$SWARM_USER]..."
        create_p4_swarm_user || return $?
    else
        get_p4_login_ticket "$SWARM_USER" "$SWARM_PASSWD" "SWARM_TICKET" || return $?
    fi

    # If we're on security=3, then use the ticket value instead of the password
    if [[ "$(get_p4_counter_value security)" == "3" ]]; then
        echo "-detected Perforce security=3; using ticket value instead of password"
        SWARM_PASSWD="$SWARM_TICKET"
    fi

    # If the password specified is a ticket, check its timeout value
    if validate_ticket "$SWARM_PASSWD"; then
        check_p4_user_login_timeout "$SWARM_USER" "$SWARM_PASSWD" || return $?
    fi

    check_p4_min_access "$SWARM_USER" "$SWARM_TICKET" "admin" ||
        warn "\
The Swarm user [$SWARM_USER] needs 'admin' access to Perforce.
Please modify the Perforce protections table to grant this privilege.
You may need the assistance of your Perforce administrator."

    return 0
}

# Create cron configuration file for Swarm cron script
configure_cron()
{
    echo "Configuring Cron..."

    # Set the Swarm hostname in the cron config file
    cat << __CRON_CONFIG__ > "$CRON_CONFIG.new"
# Perforce Swarm cron configuration
#
# Format (one per line):
# [http[s]://]<swarm-host>[:<port>]
#
$SWARM_HOST
__CRON_CONFIG__

    # Check if there's an existing config file
    if [[ -r "$CRON_CONFIG" ]]; then
        # Check if it's different from what we've just generated
        if ! diff -q "$CRON_CONFIG" "$CRON_CONFIG.new" > /dev/null; then
            echo "-renaming existing Swarm cron configuration..."
            mv -v "$CRON_CONFIG" "$CRON_CONFIG.$(date +%Y%m%d_%H%M%S)"
            mv -v "$CRON_CONFIG.new" "$CRON_CONFIG"
        else
            echo "-new Swarm cron configuration is same as existing one; removing..."
            rm -f "$CRON_CONFIG.new"
        fi
    else
        mv -v "$CRON_CONFIG.new" "$CRON_CONFIG"
    fi

    echo "-updated cron configuration file with supplied Swarm host"
}

# Configure Swarm configuration
# Replace P4PORT, Swarm user & password
# Set file permissions
configure_swarm()
{
    local php_config_export
    local new_config

    echo "Configuring Swarm installation..."
    # Use PHP to construct initial new values, and read in existing config.php
    # Anything we declare will "win", preserving other existing settings.
    local php_config_export="$(php -r "\
\$config = array(
    'environment'   => array(
        'hostname'  => '$SWARM_HOST',
    ),
    'p4' => array(
        'port'      => '$P4PORT',
        'user'      => '$SWARM_USER',
        'password'  => '$SWARM_PASSWD',
    ),
    'mail' => array(
        'transport' => array(
            'host'  => '$EMAIL_HOST',
        ),
    ),
);
file_exists('$SWARM_CONFIG') && \$config += include '$SWARM_CONFIG';
var_export(\$config);")"
    local new_config="\
<?php
return $php_config_export;"

    if [[ $? -ne 0 || -z "$php_config_export" ]]; then
        die "trouble composing new configuration file contents"
    else
        echo "-composed new Swarm config file contents"
    fi

    # Format it into our expected style
    echo "$new_config" |
        tr '\n' '\r' |
        sed -e 's/=>\s*array/=> array/g;s/array (/array(/g;s/  /    /g' |
        tr '\r' '\n' > "$SWARM_CONFIG.new"

    # Check if there's an existing config file
    if [[ -r "$SWARM_CONFIG" ]]; then
        # Check if it's different from what we've just generated
        if ! diff -q "$SWARM_CONFIG" "$SWARM_CONFIG.new" > /dev/null; then
            echo "-renaming existing Swarm config file..."
            mv -v "$SWARM_CONFIG" "$SWARM_CONFIG.$(date +%Y%m%d_%H%M%S)"
            mv -v "$SWARM_CONFIG.new" "$SWARM_CONFIG"
        else
            echo "-new Swarm config file is same as existing one; removing..."
            rm -f "$SWARM_CONFIG.new"
        fi
    else
        mv -v "$SWARM_CONFIG.new" "$SWARM_CONFIG"
        echo "-wrote new Swarm config file to reflect new configuration"
    fi

    # Ensure permissions are tight on the data directory
    local apache_user_var="APACHE_USER_${DISTRO}"
    local apache_user="${!apache_user_var}"
    [[ -n "$apache_user" ]] || die "cannot obtain APACHE_USER"

    local apache_group_var="APACHE_GROUP_${DISTRO}"
    local apache_group="${!apache_group_var}"
    [[ -n "$apache_group" ]] || die "cannot obtain APACHE_GROUP"
    echo "-identified Apache user:group: [$apache_user:$apache_group]"

    echo "-setting permissions on the Swarm data directory..."
    chown -R $apache_user:$apache_group "$SWARM_DIR/data"
    # Restrict public access to data directory due to potentially sensitive info in config.php
    chmod -R o= "$SWARM_DIR/data"
    echo "-ensured file permissions are set properly"
}

# Check Apache vhost file, and replace hostname with SWARM_HOST
# Ensure right modules are enabled
# Tweak vhost file on Apache 2.4; TODO: not just for Ubuntu?
configure_apache()
{
    echo "Configuring Apache..."
    local vhost_config_var="VHOST_CONFIG_${DISTRO}"
    local vhost_config="${!vhost_config_var}"
    [[ -s "$vhost_config" ]] || die "invalid vhost config file [$vhost_config]"
    echo "-identified Swarm virtual host config file: [$vhost_config]"

    local apache_log_dir_var="APACHE_LOG_DIR_${DISTRO}"
    local apache_log_dir="${!apache_log_dir_var}"
    [[ -d "$apache_log_dir" ]] || die "invalid Apache log directory [$apache_log_dir]"
    echo "-identified Apache log directory: [$apache_log_dir]"

    # Replace the holder for the Apache log directory in the vhost config file
    if ! sed -e "s#APACHE_LOG_DIR#${apache_log_dir}#g" "$vhost_config" > "$vhost_config.$$"; then
        rm -f "$vhost_config.$$"
        die "trouble setting Apache log dir in [$vhost_config]"
    fi
    mv -f "$vhost_config.$$" "$vhost_config"
    echo "-updated the vhost file to set Apache log directory"

    # Set the Swarm hostname in the vhost config file
    if ! sed -e "s/ServerName .*/ServerName ${SWARM_HOST}/g" "$vhost_config" > "$vhost_config.$$"; then
        rm -f "$vhost_config.$$"
        die "trouble setting ServerName in [$vhost_config]"
    fi
    mv -f "$vhost_config.$$" "$vhost_config"
    echo "-updated the vhost file to reflect Swarm host"

    # Obtain the version of Apache we're using
    local apache_version="$(apachectl -v | grep '^Server version' | sed -e 's,.*Apache/\([0-9]\.[0-9]\).*,\1,')"
    [[ -n "$apache_version" ]] || die "Cannot determine Apache version"

    # Check what version of Apache we're using
    case "$apache_version" in
    '2.4')
        # Modify the directives (for 2.2) to work with 2.4
        sed -e "/Order allow,deny/d;s,Allow from all,Require all granted," "$vhost_config" > "$vhost_config.$$"
        mv -f "$vhost_config.$$" "$vhost_config"
        echo "-updated the vhost file to handle Apache 2.4 directives"
        ;;
    '2.2')
        : # Nothing to do
        ;;
    *)
        warn "Unknown version of Apache ($apache_version)."
        ;;
    esac

    # Ensure our modules are enabled per distro
    case "$DISTRO" in
        'RHEL')
            echo "-checking Apache modules..."
            for module in 'rewrite_module' 'php5_module' ; do
                if ! apachectl -t -D DUMP_MODULES | grep -q "$module" ; then
                    die "Apache module [$module] not enabled."
                fi
            done
            echo "-proper Apache modules are enabled"

            echo "-checking Apache is configured to start on boot..."
            chkconfig httpd on
            /etc/init.d/httpd graceful
            echo "-Apache is now configured to start on boot, and is running"
            ;;
        'UBUNTU')
            echo "-checking Apache modules..."
            a2enmod rewrite php5
            echo "-proper Apache modules are enabled"

            echo "-enabling Swarm Apache site..."
            a2ensite perforce-swarm-site.conf 
            echo "-Swarm Apache site enabled"

            echo "-restarting Apache..."
            service apache2 restart
            echo "-Apache restarted"
            ;;
        *)
            die "unknown distribution [$DISTRO]"
            ;;
    esac
}

# Display what we've determined based on the supplied arguments
preamble()
{
    local notpassed="not specified"
    local passwd_there="present, but hidden"
    local suggest="will suggest"
    local p4portnote=
    local supernote=
    $INTERACTIVE &&
        notpassed="$notpassed, will prompt" ||
        suggest="will use"
    ! $CREATE &&
        notpassed="not specified" &&
        supernote=" * not needed"
    [[ ! $P4PORT_PASSED && -n "$P4PORT" ]] &&
        p4portnote=" * obtained from environment"

    cat << __PREAMBLE__
------------------------------------------------------------
$ME: $(date): commencing configuration of Swarm

Summary of arguments passed:
Interactive?       [$($INTERACTIVE && echo yes || echo no)]
Force?             [$($FORCE && echo yes || echo no)]
P4PORT             [${P4PORT:-($notpassed)}]$p4portnote
Swarm user         [${SWARM_USER:-($notpassed, $suggest "$DEFAULT_SWARM_USER")}]
Swarm password     [$([[ -n "$SWARM_PASSWD" ]] && echo "($passwd_there)" || echo "($notpassed)")]
Email host         [${EMAIL_HOST:-($notpassed)}]
Swarm host         [${SWARM_HOST:-($notpassed, $suggest "$DEFAULT_SWARM_HOST")}]
Create Swarm user? [$($CREATE && echo yes || echo no)]
Super user         [${SUPER_USER:-($notpassed)}]$supernote
Super password     [$([[ -n "$SUPER_PASSWD" ]] && echo "($passwd_there)" || echo "($notpassed)")]$supernote

__PREAMBLE__
}

#-------------------------------------------------------------------------------
# Start of main functionality
#-------------------------------------------------------------------------------

# Show usage if nothing passed
[[ -z "$1" ]] && usage_error

# Define arguments
ARGS="$(getopt -n "$ME" \
    -o "ifp:u:w:e:H:cU:W:hq" \
    -l "interactive,force,p4port:,swarm-user:,swarm-passwd:,email-host:,swarm-host:,create,super-user:,super-passwd:,help,quiet" \
    -- "$@")"
if [ $? -ne 0 ]; then
    usage_error
fi

# Reinject args from getopt, so we know they're valid and in the right order
eval set -- "$ARGS"

# Default args
INTERACTIVE=false
FORCE=false
CREATE=false
P4PORT_PASSED=false
QUIET=false

# Evaluate arguments
while true; do
    case "$1" in
        -i|--interactive)   INTERACTIVE=true;   shift ;;
        -f|--force)         FORCE=true;         shift ;;
        -p|--p4port)        P4PORT="$2";        shift 2 ; P4PORT_PASSED=true;;
        -u|--swarm-user)    SWARM_USER="$2";    shift 2 ;;
        -w|--swarm-passwd)  SWARM_PASSWD="$2";  shift 2 ;;
        -e|--email-host)    EMAIL_HOST="$2";    shift 2 ;;
        -H|--swarm-host)    SWARM_HOST="$2";    shift 2 ;;
        -c|--create)        CREATE=true;        shift ;;
        -U|--super-user)    SUPER_USER="$2";    shift 2 ;;
        -W|--super-passwd)  SUPER_PASSWD="$2";  shift 2 ;;
        -h|--help)          usage_help;         shift ;;
        -q|--quiet)         QUIET=true;  shift ;;
        --) shift ; break ;;
        *)  usage_error "command-line syntax error" ;;
    esac
done

# Sanity check
[[ ! $INTERACTIVE && -z "$P4PORT" ]] && usage_error "-p p4port required"
[[ ! $INTERACTIVE && -z "$SWARM_USER" ]] && usage_error "-u swarm-user required"
[[ ! $INTERACTIVE && -z "$SWARM_PASSWD" ]] && usage_error "-w swarm-passwd required"
[[ ! $INTERACTIVE && -z "$EMAIL_HOST" ]] && usage_error "-e email-host required"
[[ ! $INTERACTIVE && $CREATE && -z "$SUPER_USER" ]] && usage_error "Super user required"
[[ ! $INTERACTIVE && $CREATE && -z "$SUPER_PASSWD" ]] && usage_error "Super password required"

# Fail if not running as root.
[[ "$(whoami)" == "root" ]] || die "Please run this script as root"

# This script won't run correctly without a home directory
if [ -z "$HOME" ]; then export HOME="/root"; fi

# Check log we're about to start writing to
touch "$LOG" || die "Trouble opening log file [$LOG]; abort"

# Save stdout/stderr and then redirect them to the display & log
exec 3>&1 4>&2
exec &> >(tee -a "$LOG")

# Display preamble
preamble

# Determine our OS and distro
set_os_distribution

# Obtain the P4PORT
while ! check_p4port; do
    $INTERACTIVE || die "invalid P4PORT"
    cat << __P4PORT__

Swarm requires a connection to a Perforce Server.
Please supply the P4PORT to connect your Perforce Server.

__P4PORT__
    promptfor P4PORT "Perforce Server address (P4PORT)" false "$P4PORT" validate_nonempty
done

# If we need to create the Swarm user credentials
if $CREATE; then
    while ! check_super_creds; do
        $INTERACTIVE || die "invalid super user credentials"
        cat << __SWARM_USER__

To create the Swarm user, we need to use a Perforce account with 'super' rights.
Please provide a username and password for this account.

__SWARM_USER__
        promptfor SUPER_USER "Perforce username for the super user" false "$SUPER_USER" validate_nonempty
        promptfor SUPER_PASSWD "Perforce password or login ticket for the super user" true "" validate_nonempty
    done

    # Determine any user creation constraints
    check_p4_user_creation "$SUPER_USER" "$SUPER_TICKET"

    # Set any configuration stuff
    set_super_config "$SUPER_USER" "$SUPER_TICKET"
fi

# Obtain the Swarm user credentials
while ! check_swarm_creds; do
    $INTERACTIVE || die "invalid Swarm credentials"
    cat << __SUPER__

Swarm requires a Perforce user account with 'admin' rights.
Please provide a username and password for this account.

__SUPER__
    promptfor SWARM_USER "Perforce username for the Swarm user" false "${SWARM_USER:-$DEFAULT_SWARM_USER}" validate_username
    if $CREATE; then
        new_password SWARM_PASSWD "Perforce password (not a login ticket) for the Swarm user" validate_noticket
    else
        promptfor SWARM_PASSWD "Perforce password or login ticket for the Swarm user" true "" validate_nonempty
    fi
done

# Obtain the Swarm host
while [[ -z "$SWARM_HOST" ]]; do
    if ! $INTERACTIVE; then
        echo "Using default hostname [$DEFAULT_SWARM_HOST] for Swarm hostname..."
        SWARM_HOST="$DEFAULT_SWARM_HOST"
        [[ -z "$SWARM_HOST" ]] &&
            die "unable to set SWARM_HOST"
        echo "-done"
    else
        cat << __HOSTNAME__

Swarm needs to set a hostname for what it will respond to in Apache;
ensure the hostname resolves to this actual host.

__HOSTNAME__
    promptfor SWARM_HOST "Hostname for this Swarm server" false "${SWARM_HOST:-$DEFAULT_SWARM_HOST}" validate_nonempty
    fi
done

# Obtain the email relay
while [[ -z "$EMAIL_HOST" ]]; do
    $INTERACTIVE || die "invalid email host"
    cat << __EMAIL_HOST__

Swarm requires an mail relay host to send email notifications.

__EMAIL_HOST__
    promptfor EMAIL_HOST "Mail relay host (e.g.: mx.yourdomain.com)" false "" validate_nonempty
done

# Now apply configuration
configure_cron
configure_swarm
configure_apache

# All done
echo "$ME: $(date): completed configuration of Perforce Swarm"

# Restore stdout & stderr
exec >&3 >&3- 2>&4 >&4-

if ! $QUIET; then
    cat << __SUMMARY__

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::  Swarm is now configured and available at:
::
::      http://$SWARM_HOST/
::
::  You may login as the Swarm user [$SWARM_USER] using the password
::  you specified.
::
::  Please ensure you install the following package on your Perforce
::  Server host:
::
::      perforce-swarm-triggers
::
::  (If your Perforce Server host does not have an OS and platform
::  that is compatible with the above package, you can also install
::  the trigger script manually.)
::
::  You will need to configure the triggers, as covered in the Swarm
::  documentation:
::
::  http://www.perforce.com/perforce/doc.current/manuals/swarm/setup.perforce.html
::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
__SUMMARY__
fi

if [[ -n "$WARNINGS" ]]; then
    echo "============================================================"
    echo "WARNINGS DETECTED THAT MAY REQUIRE ACTION:"
    echo "$WARNINGS"
    echo "============================================================"
fi

exit 0

