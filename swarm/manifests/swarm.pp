
Exec { path => [ "/bin/", "/sbin/" , "/usr/bin/", "/usr/sbin/" ] }

# set the time zone to PDT
exec {'set time':
    command => 'echo "America/Los_Angeles" > /etc/timezone; dpkg-reconfigure --frontend noninteractive tzdata',
    unless  => 'grep "Los_Angeles" /etc/timezone',
}

file {'/vagrant/perforce':
    ensure => directory,
}

#
# Setup Perforce
#

# file {'/etc/apt/sources.list.d/p4.list':
# 	 ensure => present,
# 	 owner => root, group => root, mode => 444,
# 	 content => "deb http://package.perforce.com/apt/ubuntu precise release",
# }

# exec {'install perforce public key':
# 	 command => 'wget -q http://package.perforce.com/perforce.pubkey -O - | apt-key add -',
# 	 require => File['/etc/apt/sources.list.d/p4.list'],
# 	 unless => "apt-key list | grep Perforce",
# }

# Make use of local mirror automagically
file {'/etc/apt/sources.list':
	 ensure => present,
	 owner => root, group => root, mode => 444,
	 content => "# Reference: http://askubuntu.com/a/37754
deb mirror://mirrors.ubuntu.com/mirrors.txt precise main restricted universe multiverse
deb mirror://mirrors.ubuntu.com/mirrors.txt precise-updates main restricted universe multiverse
deb mirror://mirrors.ubuntu.com/mirrors.txt precise-backports main restricted universe multiverse
deb mirror://mirrors.ubuntu.com/mirrors.txt precise-security main restricted universe multiverse
",
}

exec {'fetch p4':
    command => 'wget -O /vagrant/perforce/p4 ftp://ftp.perforce.com/perforce/r15.1/bin.linux26x86_64/p4',
    creates => '/vagrant/perforce/p4',
    require => File['/vagrant/perforce'],
}

exec {'fetch p4d':
    command => 'wget -O /vagrant/perforce/p4d ftp://ftp.perforce.com/perforce/r15.1/bin.linux26x86_64/p4d',
    creates => '/vagrant/perforce/p4d',
    require => File['/vagrant/perforce'],
}

file {'p4':
    path => '/vagrant/perforce/p4',
    mode => 0775,
    require => Exec['fetch p4'],
}

file {'p4d':
    path => '/vagrant/perforce/p4d',
    mode => 0775,
    require => Exec['fetch p4d'],
}

file {'/usr/local/bin/p4':
    source => '/vagrant/perforce/p4',
    mode => 0555,
    require => File['p4'],
}

exec {'start p4d':
    command => '/vagrant/perforce/p4d -d -p 1666 -r /vagrant/perforce -L /vagrant/perforce/log',
    unless => "/vagrant/perforce/p4 -p 1666 info",
    require => [ File['p4d'], File['p4'] ]
}

exec {'fetch sample depot':
    command => 'wget -O /vagrant/perforce/sampledepot.tar.gz http://ftp.perforce.com/perforce/tools/sampledepot.tar.gz',
    creates => '/vagrant/perforce/sampledepot.tar.gz',
}

exec {'deploy sample depot':
    command => 'tar xfz /vagrant/perforce/sampledepot.tar.gz -C /tmp; rm -rf /vagrant/perforce/db.*; cp -Rf /tmp/PerforceSample/* /vagrant/perforce; /vagrant/perforce/p4d -r /vagrant/perforce -jr /vagrant/perforce/checkpoint; /vagrant/perforce/p4 -p 1666 -u bruno passwd -P fooBARbaz; echo fooBARbaz|/vagrant/perforce/p4 -p 1666 -u bruno login; cat /vagrant/manifests/files/p4grp.registered|/vagrant/perforce/p4 -p 1666 -u bruno group -i ',
    require => [ Exec['fetch sample depot'], Exec['start p4d'] ],
    unless  => "find /vagrant/perforce -name jam | grep jam",
}

# To deploy live data, do the following:
#   - service perforce-server stop
#   - rm /vagrant/perforce/db.* /vagrant/perforce/journal
#   - /vagrant/perforce/p4d -r /vagrant/perforce -K db.have -jr -z /vagrant/perforce/ckp.gz
#   - install license
#   - fix protection table for "swarm-user":
#     admin user swarm-user 127.0.0.1 //guest/...
#     admin user swarm-user 127.0.0.1 //public/...
#     admin user swarm-user 127.0.0.1 //.swarm/...
#   - fix up password for "swarm-user"
#   - fix up ticket in config.php
#   - re-create the group "registered" add "swarm-user" and yourself to it
# FIXME: Need to automate all these....

# setup a startup script for p4d
file {'/etc/init/perforce-server.conf':
    source => '/vagrant/manifests/files/perforce-server.conf',
    ensure  => 'present',
    owner   => root, group => root, mode => 644,
    require => [ File['p4d'], File['p4'] ]
}

#
# Setup Swarm
#
class { 'apache':
        mpm_module => 'prefork',
        default_vhost => false,
      }

include apache::mod::php

apache::mod { 'rewrite': }

exec { "apt-get update":
    path => "/usr/bin",
}

package { "apt-show-versions":
    ensure => present,
    require => Exec["apt-get update"],
}

package { "php-pear":
    ensure => present,
    require => [ Exec["apt-get update"], Package['build-essential'] ],
}

package { "php-apc":
    ensure => present,
    require => [ Exec["apt-get update"], Package['php-pear'] ],
}

exec { 'get xdebug':
    command => 'pecl install xdebug-2.2.7',
    require =>  Package['php-pear'],
    unless  =>  'find /usr/lib -name xdebug.so | grep xdebug',
}

package { "imagemagick":
    ensure => present,
    require => [ Exec["apt-get update"] ],
}

# package { "phpunit":
#     ensure => present,
#     require => [ Exec["apt-get update"] ],
# }

package { "ant":
    ensure => present,
    require => [ Exec["apt-get update"], Package["openjdk-7-jdk"] ],
}

package { "openjdk-7-jdk":
    ensure => present,
    require => [ Exec["apt-get update"] ],
}

# exec { "get phpunit":
#     command => 'pear upgrade pear; pear channel-discover pear.phpunit.de; pear channel-discover components.ez.no; pear channel-discover pear.symfony.com; pear install --alldeps phpunit/PHPUnit; pear install --alldeps PhpDocumentor;',
#     require =>  [ Package['phpunit'], Package['php-pear'] ],
#     unless  =>  'phpunit --version',
# }


# These are optional packages. They allow Swarm to preview Office files and
# non-web safe images. They are nice to have, but take awhile to download.

#package { "libreoffice-calc":
#    ensure => present,
#    require => Exec["apt-get update"],
#}

#package { "libreoffice-draw":
#    ensure => present,
#    require => Exec["apt-get update"],
#}

#package { "libreoffice-impress":
#    ensure => present,
#    require => Exec["apt-get update"],
#}

#package { "libreoffice-writer":
#    ensure => present,
#    require => Exec["apt-get update"],
#}

package { "build-essential":
    ensure => present,
    require => Exec["apt-get update"],
}

apache::vhost { 'localhost':
  port        => '80',
  docroot     => '/var/www/swarm/public',
  serveradmin => 'admin@example.com',
  directories => [ { path          => '/var/www/swarm/public',
                    allow_override => ['All'] },
                    order => 'Allow, Deny',
                    allow => 'from all'
                 ],
  error_log_file    => 'swarm.error.log',
  access_log_file   => 'swarm.access.log',
  access_log_format => 'combined',
  docroot_group     => 'www-data',
  docroot_owner     => 'www-data',
  require           => [ Package['php-apc'], Exec['deploy sample depot'], File['/var/www/swarm/data'] ],
  notify            => Service['httpd'],
}

# create the data directory that Swarm needs
file {'/var/www/swarm/data':
  ensure => directory,
  mode => 0770,
  owner => 'www-data',
  group => 'www-data',
}

# copy the P4PHP module some place in the VM so that Apache doesn't need a restart on boot
file {'/etc/php5/perforce-php53.so':
  source => '/vagrant/p4-bin/bin.linux26x86_64/perforce-php53.so',
  ensure => present,
  owner => root, group => root, mode => 444,
  require => Package['php-pear'],
}

# this adds P4PHP support to PHP.
file {'/etc/php5/conf.d/p4php.ini':
  ensure => present,
  owner => root, group => root, mode => 444,
  content => "extension=/etc/php5/perforce-php53.so\n",
  require => File['/etc/php5/perforce-php53.so'],
}

# this adds xdebug support to PHP.
file {'/etc/php5/conf.d/xdebug.ini':
  ensure => present,
  owner => root, group => root, mode => 444,
  content => "zend_extension=/usr/lib/php5/20090626/xdebug.so\nxdebug.remote_enable=1\nxdebug.remote_handler=dbgp\nxdebug.remote_connect_back=1\n",
  require => File['/etc/php5/perforce-php53.so'],
}

# create a configuration file under data
file { "/var/www/swarm/data/config.php":
  source => "/vagrant/manifests/files/config.php",
  ensure => file,
  require => File['/var/www/swarm/data'],
}

exec {"restart swarm":
  require => [ File['/var/www/swarm/data/config.php'], File['/etc/php5/conf.d/p4php.ini'],Apache::Vhost['localhost'] ],
  command => "service apache2 restart",
  creates => '/var/www/swarm/data/queue/tokens',
}

exec {"wait for swarm":
  require => [ File['/var/www/swarm/data/config.php'], File['/etc/php5/conf.d/p4php.ini'],Exec['restart swarm'] ],
  command => "/usr/bin/wget --spider --tries 10 --retry-connrefused  http://localhost/login",
  creates => '/var/www/swarm/data/queue/tokens',
}

# stick the output somewhere we can read jackass
# establish the trigger token
exec { 'login to swarm':
  command => 'wget -o /dev/null -O /dev/null  --keep-session-cookies  --save-cookies /var/www/swarm/data/cjar --post-file /vagrant/manifests/files/pfile http://localhost/login',
  require => [ File['/var/www/swarm/data/config.php'], File['/etc/php5/conf.d/p4php.ini'], Exec['wait for swarm'] ],
  creates => '/var/www/swarm/data/queue/tokens',
}

# generate the auth token that Swarm needs for the triggers
exec { 'generate token':
  command => 'wget -o /dev/null -O /dev/null --load-cookies /var/www/swarm/data/cjar http://localhost/about',
  require => [ Exec['login to swarm'], File['/var/www/swarm/data/config.php'], File['/etc/php5/conf.d/p4php.ini'] ],
  creates => '/var/www/swarm/data/queue/tokens',
}

# add custom directory in public for people to drop their js/css
file { '/vagrant/public/custom':
    ensure => directory,
    mode => 775,
}

# add custom directory in public for people to drop their js/css
file { '/vagrant/logs':
    ensure => directory,
    group => 'www-data',
    mode => 775,
}

file { '/vagrant/logs/email':
    ensure => directory,
    group => 'www-data',
    mode => 775,
}

# the apache error log
file { '/vagrant/logs/apache.error.log':
   ensure => 'link',
   target => '/var/log/apache2/error.log',
}

# the swarm apache vhost log
file { '/vagrant/logs/swarm.error.log':
   ensure => 'link',
   target => '/var/log/apache2/swarm.error.log',
}

# the swarm log
file { '/vagrant/logs/swarm_log':
   ensure => 'link',
   target => '/var/www/swarm/data/log',
}

# generate demo data
exec { 'generate demo data':
  command => 'wget -o /vagrant/logs/demolog -O /dev/null --load-cookies /var/www/swarm/data/cjar http://localhost/demo/generate?force=1',
  require => Exec['generate token'],
  creates => '/vagrant/logs/demolog',
}

# setup cronjob
cron { 'worker':
  command => "wget -q -O /dev/null -T5 http://localhost/queue/worker",
  ensure  => present,
  user    => vagrant,
}

# replace the UUID
exec { 'set token':
  command => 'sed -i "s:^SWARM_TOKEN=.*:SWARM_TOKEN=\"$(sudo ls -1 /var/www/swarm/data/queue/tokens | tr -d \'\n\')\":" /vagrant/p4-bin/scripts/swarm-trigger.sh',
  unless => 'grep -e "$(sudo ls -1 /var/www/swarm/data/queue/tokens | tr -d \'\n\')" /vagrant/p4-bin/scripts/swarm-trigger.sh',
  require => Exec['generate token'],
}

# replace the hostname
exec { 'set hostname':
  command => 'sed -i "s|^SWARM_HOST=\"http://my-swarm-host\"|SWARM_HOST=\"http://localhost\"|" /vagrant/p4-bin/scripts/swarm-trigger.sh',
  onlyif => 'grep -e "SWARM_HOST=\"http://my-swarm-host\"" /vagrant/p4-bin/scripts/swarm-trigger.sh',
  require => Exec['generate token'],
}

# put the triggers in Perforce
exec { 'update triggers':
  command => 'echo "$(/vagrant/perforce/p4 -p 1666 -u bruno -P fooBARbaz triggers -o)" "$(bash /vagrant/p4-bin/scripts/swarm-trigger.sh -o)" | /vagrant/perforce/p4 -p 1666 -u bruno -PfooBARbaz triggers -i',
  unless => '/vagrant/perforce/p4 -p 1666 -u bruno -P fooBARbaz triggers -o | grep -e "swarm.user"',
  require => Exec['set hostname'],
}

# Local Variables:
# mode: puppet
# End Variables:
