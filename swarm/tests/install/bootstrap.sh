#!/bin/bash

# Grab the external IP
if command -v wget > /dev/null
then
    external_ip="`wget -qO - http://ifconfig.me/ip`"
elif command -v curl > /dev/null
then
    external_ip="`curl -s http://ifconfig.me/ip`"
else
    echo "wget nor curl found"
fi

# If we're on Red Hat...
if [ -e "/etc/redhat-release" ]; then
    chkconfig iptables off
    service iptables stop

    cat << "P4REPO" > /etc/yum.repos.d/perforce.repo
[perforce-local]
name=Perforce Local Packages for CentOS $releasever - $basearch
baseurl=file:///home/vagrant/p4packages
#gpgkey=http://pkg-ondemand.bnr.perforce.com/perforce.pubkey
enabled=0
gpgcheck=0

[perforce-main]
name=Perforce Main-Branch Packages for CentOS $releasever - $basearch
baseurl=http://pkg-ondemand.bnr.perforce.com/swarm/main/yum/rhel/$releasever/$basearch/
gpgkey=http://pkg-ondemand.bnr.perforce.com/perforce.pubkey
enabled=1
gpgcheck=1

[perforce-candidate]
name=Perforce Candidate-Branch Packages for CentOS $releasever - $basearch
baseurl=http://pkg-ondemand.bnr.perforce.com/swarm/candidate/yum/rhel/$releasever/$basearch/
gpgkey=http://pkg-ondemand.bnr.perforce.com/perforce.pubkey
enabled=0
gpgcheck=1

[perforce-staging]
name=Perforce Staging Packages for CentOS $releasever - $basearch
baseurl=http://pkg-stage.perforce.com/yum/rhel/$releasever/$basearch/
gpgkey=http://pkg-stage.perforce.com/perforce.pubkey
enabled=0
gpgcheck=1

[perforce-public]
name=Perforce Public Packages for CentOS $releasever - $basearch
baseurl=http://package.perforce.com/yum/rhel/$releasever/$basearch/
gpgkey=http://package.perforce.com/perforce.pubkey
enabled=0
gpgcheck=1
P4REPO

    rpm --import http://pkg-ondemand.bnr.perforce.com/perforce.pubkey
    rpm --import http://pkg-stage.perforce.com/perforce.pubkey
    rpm --import http://package.perforce.com/perforce.pubkey
    rpm -q gpg-pubkey --qf '%{name}-%{version}-%{release} --> %{summary}\n'

    # Do not 'yum clean all' -- it will blow away your vagrant-cachier cache!
    yum clean expire-cache
    rpm -ivh http://download.fedoraproject.org/pub/epel/6/i386/epel-release-6-8.noarch.rpm
    #time -p yum -y update

    # install Perforce Swarm dependencies
    time -p yum -y install httpd php php-pecl-apc wget

    # install Perforce Swarm optional dependencies
    #time -p yum -y install php-pecl-imagick libreoffice-{calc,draw,headless,impress,writer}

    PACKAGE_TYPE="RPM"

# If we're on Debian (Ubuntu)...
elif [ -e "/etc/debian_version" ]; then

    proxy=""

    # Alameda
    [ "external_ip" = "107.1.244.230" ] &&
        proxy="http://package-os.perforce.com:3142"

    # Victoria
    [ "$external_ip" = "69.196.72.54" ] &&
        proxy="" #proxy="http://tachyon.perforce.ca:3142"

    [ -n "$proxy" ] &&
        echo 'Acquire::http { Proxy "$proxy"; };' | sudo tee /etc/apt/apt.conf.d/02proxy

    # Don't use default repositories; hardcoded to GB.
    # Use mirrors to find nearest repository to appliance instead.
    (
        #uri=mirror://mirrors.ubuntu.com/mirrors.txt
        #uri=http://mirror.it.ubc.ca/ubuntu/
        uri=http://mirror.pnl.gov/ubuntu
        rel=precise
        com='main restricted universe multiverse'

        echo deb $uri $rel           $com
        echo deb $uri $rel-updates   $com
        echo deb $uri $rel-backports $com
        echo deb $uri $rel-security  $com
    ) > /etc/apt/sources.list.d/mysources.list
    chmod 644 /etc/apt/sources.list.d/mysources.list
    mv /etc/apt/sources.list /etc/apt/sources.list.default

    cat << "P4SOURCES" > /etc/apt/sources.list.d/perforce.list
# Local packages you just built
#deb file:///home/vagrant/p4packages /

# Main-branch packages
deb http://pkg-ondemand.bnr.perforce.com/swarm/main/apt/ubuntu/ precise release

# Candidate-branch packages
#deb http://pkg-ondemand.bnr.perforce.com/swarm/candidate/apt/ubuntu/ precise release

# Staging packages
#deb http://pkg-stage.perforce.com/apt/ubuntu/ precise release

# Public packages
#deb http://package.perforce.com/apt/ubuntu/ precise release
P4SOURCES

    # Import our package signing key
    wget -qO - http://pkg-ondemand.bnr.perforce.com/perforce.pubkey | apt-key add -
    wget -qO - http://pkg-stage.perforce.com/perforce.pubkey | apt-key add -
    wget -qO - http://package.perforce.com/perforce.pubkey | apt-key add -
    apt-key list

    time -p apt-get update -y
    echo grub-pc hold | dpkg --set-selections
    time -p apt-get upgrade -y

    # install Perforce Swarm dependencies
    time -p apt-get install -y apache2 libapache2-mod-php5 php-apc php5-cli php5-json wget

    # install Perforce Swarm optional dependencies
    time -p apt-get install -y libreoffice-calc libreoffice-draw libreoffice-impress libreoffice-writer php5-imagick

    PACKAGE_TYPE="DEB"

else
    echo "unknown distro; not updating nor installing any packages"
fi

# Set our environment config
cat << ! > /etc/profile.d/p4-dev.sh
P4CONFIG=.p4config
DEBFULLNAME="Perforce Software"
DEBEMAIL="perforce+packages@perforce.com"
export P4CONFIG DEBFULLNAME DEBEMAIL
!
echo "p4-dev environment file created:"
echo "vvvvvvvvvv"
cat /etc/profile.d/p4-dev.sh
echo "^^^^^^^^^^"

cat << !
------------------------------------------------------------
Bootstrap for Installing Swarm Packages complete
!
