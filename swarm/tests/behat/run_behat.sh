#!/bin/bash
ME="$(basename "$0")"
MYDIR="$(cd "$(dirname "$0")"; pwd)"

usage()
{
    cat << ! >&2

Usage: $ME -b <browser> -p <p4dver> [-s <swarmhost>] [-u] [-v] [-h]
    -b: specify browser: firefox
    -p: specify p4d version (see tests/p4-bin)
    -s: specify swarm host (default is localhost)
    -u: update the composer.phar file
    -v: verbose behat console output
    -h: prints out the usage text

!
    exit 1
}

DEFAULT_SWARMHOST=localhost
UPDATE_COMPOSER=0
VERBOSE=""
while getopts ":b:p:s:uvh" VALUE
do
    case "$VALUE" in
    b)  BROWSER="$OPTARG" ;;
    p)  P4DVER="$OPTARG" ;;
    s)  SWARMHOST="$OPTARG" ;;
    u)  UPDATE_COMPOSER=1 ;;
    v)	VERBOSE="--verbose" ;;
    h)  usage ;;
    *)  echo "unknown flag [-$OPTARG]"; exit ;;
    esac
done

[ -z "$BROWSER" ] && echo "error: browser (-b) not specified" && usage
[ -z "$P4DVER" ] && echo "error: p4dver (-v) not specified" && usage
[ -z "$SWARMHOST" ] && SWARMHOST="$DEFAULT_SWARMHOST"

cat << !
==========================================
$(date): Starting Behat tests with:
Browser        [$BROWSER]
P4D version    [$P4DVER]
Swarm hostname [$SWARMHOST]

!

cd "$MYDIR"

# Retrieve the composer.phar if it does not exist
if [[ ! -r "composer.phar" || $UPDATE_COMPOSER -eq 1 ]]
then
    curl --silent http://getcomposer.org/installer | php
fi

# Install/update our dependencies
php composer.phar install

# Set our profile based on browser and p4d version
PROFILE="${BROWSER}_p4d${P4DVER}"

# Craft our config file
sed "s/CHANGE_ME/http:\/\/$SWARMHOST/g" config/behat.yml.template > config/behat.yml
rm -f rerun.out "${PROFILE}.html"

# Run the Behat tests
CMD="php bin/behat \
    --expand \
    $VERBOSE \
    --format="pretty,html" \
    --out=",${PROFILE}.html" \
    --profile="${PROFILE}" \
    --rerun="rerun.out" \
    --ansi \
    --tags="~@wip" \
    features/"
echo "Running: [$(echo $CMD | sed -e 's,  , ,g')]..."
$CMD
RC=$?

exit $RC
