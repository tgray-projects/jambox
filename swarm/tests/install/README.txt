README for installing packages in your own dev environment

------------------------------------------------------------
Prerequisites (copied from collateral/packaging)
------------------------------------------------------------

1. Install VirtualBox (vagrant does work with VMWare, but not as well):

    https://www.virtualbox.org/wiki/Downloads

2. Install Vagrant:

    http://www.vagrantup.com/downloads.html

3. Make sure you have a workspace view like the following:

    //depot/main/swarm/...                    //workspace/swarm/...
    //depot/main/packaging/*                  //workspace/packaging/*
    //depot/main/p4-bin/bin.linux26x86_64/jam //workspace/bin/jam
    //depot/r13.2/p4-bin/bin.linux26x86_64/p4 //workspace/bin/p4

------------------------------------------------------------
Process
------------------------------------------------------------

4. Start the Vagrant VM box:

    $ cd swarm/tests/install/<ubuntu-12.04 or centos-6.5>
    $ mkdir -p ../../../../p4-bin
    $ vagrant up

    This will:
    * download the Vagrant box (if necessary; no need to manually do so)
    * boot it up
    * configure system services
    * map your workspace root (where the 'p4-bin' directory that holds the built packages)
    * update and install Swarm package pre-requisites

6. Login

    $ vagrant ssh

7a. Install a package you built
    (assumes you built them via instructions in collateral/packaging)

    $ cd p4packages
    $ ls -l

    # For RPMs:
    $ sudo rpm -ivh perforce-swarm-<whatever>.rpm

    # for DEBs:
    $ sudo dpkg -i perforce-swarm-<whatever>.deb

    This will install the package.

    Note that uninstalled dependencies won't be installed, so you may have
    to specify multiple packages; e.g.:
    perforce-swarm-<version>.<ext> + perforce-swarm-r14.2-<version>.<ext>

7b. Install a mainline package built from EC:

    # for DEBs:
    $ apt-cache search perforce-
    $ sudo apt-get install perforce-swarm<whatever>

    # For RPMs:
    $ yum search perforce-
    $ sudo yum install perforce-swarm<whatever>

    This will install the package and any uninstalled dependencies.

8. You can modify the repo config file to choose a different repo:
    * Local repo in ~/p4packages (what you built from collateral/packaging/README.txt)
    * On-demand main branch repo (what's built from the main branch)
    * On-demand candidate branch repo (what's built from the candidate branch)
    * Staging repo
    * Public repo (what our customers see)

    Debian repo (apt) config file:
    /etc/apt/sources.list.d/perforce.list

    RPM repo (yum) config file:
    /etc/yum.repos.d/perforce.repo