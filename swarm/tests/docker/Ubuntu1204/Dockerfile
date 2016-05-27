# Dockerfile test

# Base image is Ubuntu

FROM ubuntu:12.04

MAINTAINER Matthew Birky, mbirky@perforce.com

# Updating and installing required packages
RUN apt-get -y update && apt-get install -y net-tools rsyslog curl cron dpkg-dev uuid-runtime udev wget

# Adding p4, p4d, and packages
ADD p4 /usr/bin/
ADD p4d /usr/local/bin/
ADD swarm.deb.tgz /tmp/
ADD perforce.deb.tgz /tmp/

# Creating local package repo
RUN cd /tmp/apt/ubuntu/incoming && dpkg-scanpackages . /dev/null | gzip -c9 > Packages.gz
RUN echo "deb file:///tmp/apt/ubuntu/incoming/ /" > /etc/apt/sources.list.d/perforce.list
RUN apt-get update
RUN apt-get install --allow-unauthenticated -y perforce-swarm

# Running script to configure perforce and swarm
ADD swarm-setup.sh /tmp/
ADD swarm_package_sanity.sh /tmp/
RUN rsyslogd && cron && . /tmp/swarm-setup.sh && /tmp/swarm_package_sanity.sh

