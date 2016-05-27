# Dockerfile test

FROM centos:6.6

MAINTAINER Matthew Birky, mbirky@perforce.com

# Updating and installing required packages
RUN yum install -y net-tools rsyslog curl vixie-cron dpkg-dev uuid-runtime udev wget

# Adding p4, p4d, and packages
#ADD p4 /usr/bin/
#ADD p4d /usr/local/bin/
ADD swarm.rpm.tgz /tmp/
ADD perforce.rpm.tgz /tmp/

RUN cd /tmp/yum/rhel/6/x86_64 && rpm -ivh --nosignature --nodeps perforce-server-* perforce-cli-* && yum install -y perforce-swarm-20*

# Running script to configure perforce and swarm
ADD swarm-setup.sh /tmp/
ADD swarm_package_sanity.sh /tmp/
RUN service rsyslog start && service crond start && . /tmp/swarm-setup.sh && /tmp/swarm_package_sanity.sh
