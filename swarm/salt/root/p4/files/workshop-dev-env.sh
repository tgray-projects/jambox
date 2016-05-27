#!/bin/sh

P4PORT=1666
P4SUPER=super
P4PASSWD=swarm
P4NAME=swarm
P4ROOT="/srv/p4/$P4NAME"
SWARMUSER=swarm
P4="/usr/bin/p4 -p $P4PORT -u $P4SUPER"
SWARMDATADIR=/var/www/swarm/shared/data

# # define a server
# /opt/perforce/sbin/configure-perforce-server.sh $P4NAME -p $P4PORT -u $P4SUPER -P $P4PASSWD -r $P4ROOT -n
# # security=0 - for demo!
# /usr/sbin/p4d -r $P4ROOT '-cset security=0'

test -d $P4ROOT || mkdir -p $P4ROOT
chown -R perforce: $P4ROOT

# restore checkpoint and nuke password for user "perforce"
# zcat /vagrant/perforce/ckp.gz|perl -pe 's/(\S+) (\d+) (\@db.user\@) (\@perforce\@) (\S+) (\@\@) (\d+) (\d+) (\@Perforce maintenance\@) ([\@A-Z0-9]+) (.+)/$1 $2 $3 $4 $5 $6 $7 $8 $9 @@ $11/'|p4d -r $P4ROOT -K db.have -jr -

# restore from checkpoint
p4d -r $P4ROOT -K db.have -jr -z /vagrant/perforce/ckp.gz
# remove all users and protection
rm -f $P4ROOT/db.protect $P4ROOT/db.user* $P4ROOT/db.trigger || true
# allow creating new users
cat << EOF | p4d -r $P4ROOT -jr -
@dv@ 1 @db.config@ @any@ @dm.user.noautocreate@ @2@
EOF
# fix up permissions
chown -R perforce: $P4ROOT

cat <<EOF > $P4ROOT/license
# for testing only
License:	0A3F3CC4FAC5D31BC191E904A24ED04E

License-Expires:	1464069553	# 2016/05/24

Customer:	Perforce Software, Inc.

IPaddress:	127.0.0.1

Users:	5000
EOF

cat <<EOF > /etc/perforce/p4dctl.conf.d/swarm.conf
p4d swarm
{
    Owner    =	perforce
    Execute  =	/opt/perforce/sbin/p4d
    Umask    =	077

    # Enabled by default.
    Enabled  =	true

    Environment
    {
        P4ROOT    =	$P4ROOT
        P4JOURNAL =	journal
        P4PORT    =	1666
        P4SSLDIR  =	ssl
        PATH      =	/bin:/usr/bin:/usr/local/bin:/opt/perforce/bin:/opt/perforce/sbin
    }

}
EOF

[ -d /p4 ] || mkdir /p4
[ -L /p4/1 ] || ln -s /srv/p4/swarm /p4/1

p4dctl start swarm

# create super
$P4 user -o |$P4 user -i

# create Swarm user and setup sane group defaults
$P4 user -o $SWARMUSER | $P4 user -i -f
$P4 passwd -P $P4PASSWD $SWARMUSER
echo $P4PASSWD | /usr/bin/p4 -p $P4PORT -u $P4SUPER login
cat <<EOF | $P4 group -i
Group:	adm
MaxResults:	unset
MaxScanRows:	unset
MaxLockTime:	unset
Timeout:	43200
PasswordTimeout:	unset
Subgroups:
Owners:
	$P4SUPER
Users:
	$SWARMUSER
EOF

cat <<EOF | $P4 group -i
Group:	background
MaxResults:	unset
MaxScanRows:	unset
MaxLockTime:	unset
Timeout:	unlimited
PasswordTimeout:	unset
Subgroups:
Owners:
	$P4SUPER
Users:
	$SWARMUSER
	$P4SUPER
EOF

cat <<EOF | $P4 group -i
Group: registered
Users:
	$P4SUPER
	$SWARMUSER
EOF

cat <<EOF | $P4 protect -i
Protections:
        write user * * //...
        admin group adm * //...
        super user lcheung * //...
        super user $P4SUPER * //...
EOF

cat <<EOF > /root/.p4config
P4PORT=1666
P4USER=$P4SUPER
EOF

# configure Swarm

# setup Swarm token
mkdir -p $SWARMDATADIR/queue/tokens
touch $SWARMDATADIR/queue/tokens/00000000-0000-0000-0000-000000000000
chown -R www-data: $SWARMDATADIR

# config for swarm triggers
cat <<EOF > /etc/perforce/swarm-trigger.conf
SWARM_HOST=http://localhost
SWARM_TOKEN=00000000-0000-0000-0000-000000000000
EOF

# Swarm configuration
cat <<EOF > $SWARMDATADIR/config.php
<?php
return array(
    'environment' => array(
        'hostname' => 'p4',
    ),
    'p4' => array(
        'port' => '1666',
        'user' => 'swarm',
        'password' => "$P4PASSWD",
    ),
    'mail' => array(
        'transport' => array(
            //'host' => 'localhost',
            'transport'  => array('path' => "$SWARMDATADIR/mail"),

        ),
    ),
);
EOF

cat <<EOF | /usr/bin/p4 -p $P4PORT -u $P4SUPER triggers -i
Triggers:
	swarm.job        form-commit   job    "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t job          -v %formname%"
	swarm.user       form-commit   user   "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t user         -v %formname%"
	swarm.userdel    form-delete   user   "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t userdel      -v %formname%"
	swarm.group      form-commit   group  "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t group        -v %formname%"
	swarm.groupdel   form-delete   group  "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t groupdel     -v %formname%"
	swarm.changesave form-save     change "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t changesave   -v %formname%"
	swarm.shelve     shelve-commit //...  "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t shelve       -v %change%"
	swarm.commit     change-commit //...  "%quote%/opt/perforce/swarm-triggers/bin/swarm-trigger.sh%quote% -t commit       -v %change%"
EOF


# p4php
PHPVER=`php --version|head -1|cut -d' ' -f2|cut -d. -f1,2|sed 's|\.||g'`
cat <<EOF > /etc/php5/apache2/conf.d/p4php.ini
extension=/opt/perforce/swarm/p4-bin/bin.linux26x86_64/perforce-php${PHPVER}.so
EOF


# apache
cat <<EOF
<VirtualHost *:80>
        ServerAdmin lcheung@perforce.com

        ServerName wayfarer-swarm-stage.perforce.com
        ServerAlias localhost

        DocumentRoot "/var/www/swarm/current/public"
        <Directory '/var/www/swarm/current/public'>
                Options FollowSymLinks
                AllowOverride All
                Order allow,deny
                allow from all
        </Directory>

        # ErrorLog /var/log/apache2/swarm.error.log
        # CustomLog /var/log/apache2/swarm.access.log combined

        ErrorLog ${APACHE_LOG_DIR}/swarm.error.log
        # Possible values include: debug, info, notice, warn, error, crit,
        # alert, emerg.
        LogLevel warn
        CustomLog ${APACHE_LOG_DIR}/swarm.access.log combined
</VirtualHost>
EOF
