#!/bin/sh

P4PORT=1666
P4SUPER=super
P4PASSWD=iamsuperm4n
P4NAME=swarm
P4ROOT="/srv/p4/$P4NAME"
SWARMUSER=swarm
P4="/usr/bin/p4 -p $P4PORT -u $P4SUPER"
SWARMDATADIR=/opt/perforce/swarm/data

# define a server
/opt/perforce/sbin/configure-perforce-server.sh $P4NAME -p $P4PORT -u $P4SUPER -P $P4PASSWD -r $P4ROOT -n

# security=0 - for demo!
/usr/sbin/p4d -r $P4ROOT '-cset security=0'

# login super user
login_super_user(){echo $P4PASSWD | /usr/bin/p4 -p $P4PORT -u $P4SUPER login}
login_super_user
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

cat <<EOF
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
        super user super * //...
EOF

cat <<EOF > /root/.p4config
P4PORT=1666
P4USER=super
EOF
# get a ticket with no timeout
login_super_user

# p4php
PHPVER=`php --version|head -1|cut -d' ' -f2|cut -d. -f1,2|sed 's|\.||g'`
cat <<EOF > /etc/php5/apache2/conf.d/p4php.ini
extension=/opt/perforce/swarm/p4-bin/bin.linux26x86_64/perforce-php${PHPVER}.so
EOF

# configure Swarm
# configure-swarm.sh needs /opt/perforce/etc for temp files
mkdir /opt/perforce/etc
# configure-swarm.sh expects the vhost template in the right place
cp -a /vagrant/packaging/perforce-swarm-site.conf /etc/apache2/sites-available/perforce-swarm-site.conf

mkdir /opt/perforce/swarm/data
chown www-data: /opt/perforce/swarm/data
ln -s /opt/perforce/swarm/data /var/www/swarm/data

# /opt/perforce/swarm/packaging/configure-swarm.sh -p $P4PORT -u $SWARMUSER -w $P4PASSWD -e localhost -U $P4SUPER -W $P4PASSWD
/vagrant/packaging/configure-swarm.sh -p $P4PORT -u $SWARMUSER -w $P4PASSWD -e localhost -U $P4SUPER -W $P4PASSWD


# # setup Swarm token
# mkdir -p $SWARMDATADIR/queue/tokens
# touch $SWARMDATADIR/queue/tokens/00000000-0000-0000-0000-000000000000
# chown -R www-data: $SWARMDATADIR/queue

# cat <<EOF > /etc/perforce/swarm-trigger.conf
# SWARM_HOST=http://localhost
# SWARM_TOKEN=00000000-0000-0000-0000-000000000000
# EOF

# # Swarm configuration
# cat <<EOF > /opt/perforce/swarm/data/config.php
# <?php
# return array(
#     'environment' => array(
#         'hostname' => 'p4',
#     ),
#     'p4' => array(
#         'port' => '1666',
#         'user' => 'swarm',
#         'password' => "$P4PASSWD",
#     ),
#     'mail' => array(
#         'transport' => array(
#             //'host' => 'localhost',
#             'transport'  => array('path' => "/opt/perforce/swarm/data/mail"),

#         ),
#     ),
# );
# EOF

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
