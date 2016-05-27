################################################################################
# Perforce Swarm RPM Spec File
#
# Packages specified:
# helix-swarm
# helix-swarm-optional
# helix-swarm-triggers

################################################################################
# Defines and Macros

# Suppress building a debuginfo package
%define debug_package           %{nil}

%define PERFORCE_ROOT           /opt/perforce
%define PERFORCE_CFGDIR         %{PERFORCE_ROOT}/etc
%define SWARM_ROOT              %{PERFORCE_ROOT}/swarm
%define SWARM_DATADIR           %{SWARM_ROOT}/data
%define SWARM_CONFIG            %{SWARM_DATADIR}/config.php
%define SWARM_SBINDIR           %{SWARM_ROOT}/sbin

%define APACHE_CFGDIR           /etc/httpd/conf.d/
%define SWARM_VHOST             %{APACHE_CFGDIR}/perforce-swarm-site.conf
%define APACHE_USER             apache
%define APACHE_GROUP            apache

%define CRON_DIR                /etc/cron.d
%define CRON_SCRIPT             %{CRON_DIR}/helix-swarm
%define CRON_SCRIPT_OLD         %{CRON_DIR}/perforce-swarm
%define CRON_CONFIG             %{PERFORCE_CFGDIR}/swarm-cron-hosts.conf

%define PHPINI_DIR              /etc/php.d
%define P4PHP_INI               %{PHPINI_DIR}/perforce.ini

%define SWARM_TRIG_BINDIR       %{PERFORCE_ROOT}/swarm-triggers/bin
%define SWARM_TRIG_SCRIPT_SRC   p4-bin/scripts/swarm-trigger.pl
%define SWARM_CONFIG_SRCNAME    swarm-trigger.conf
%define SWARM_TRIG_CONFIG       %{PERFORCE_CFGDIR}/%{SWARM_CONFIG_SRCNAME}

################################################################################
# Sources

Source0:    swarm.tgz
Source1:    configure-swarm.sh
Source2:    perforce-swarm-site.conf
Source3:    perforce-swarm.cron

################################################################################
# Package info

Name:       helix-swarm
Version:    %{p4rel_base}
Release:    %{p4change}%{dist}
Summary:    Helix Swarm: Beautiful code review for your beautiful code
Vendor:     Perforce Software, Inc.
Group:      application
License:    Modified BSD 2-Clause License
URL:        http://www.perforce.com/swarm

Requires:   perforce-cli-base
Requires:   httpd >= 2.2.15
Requires:   php >= 5.3.3
Requires:   php-pecl-apc >= 3.1
Requires:   php-xml >= 5.3.3
Requires:   php-mbstring >= 5.3.3
Requires:   wget
Conflicts:  perforce-swarm-r14.2
Obsoletes:  perforce-swarm
AutoReq:    no

%description
Helix Swarm enables code review for teams using Perforce to help you ship
quality software faster. Review code before or after committing it, bring
continuous integration into the review, and commit work that passes review.

#=========#=========#=========#=========#=========#=========#=========#=========
%package    optional
Summary:    Optional preview tools for Helix Swarm
Requires:   helix-swarm
Requires:   php-pecl-imagick
Requires:   libreoffice-calc
Requires:   libreoffice-draw
Requires:   libreoffice-headless
Requires:   libreoffice-impress
Requires:   libreoffice-writer
Conflicts:  perforce-swarm-r14.2-optional
Obsoletes:  perforce-swarm-optional

%description optional
Optional preview tools for Helix Swarm

This package depends on packages which are available from the EPEL project.
In order to install packages from EPEL, you will need to add the EPEL
repository and accept its signing key (e.g. 'yum install epel-release').
Detailed instructions are available at https://fedoraproject.org/wiki/EPEL

#=========#=========#=========#=========#=========#=========#=========#=========
%package triggers
Summary:    Trigger script for Helix Swarm
Requires:   wget
Requires:   perforce-cli-base
Conflicts:  helix-swarm-r14.2-triggers
Obsoletes:  perforce-swarm-triggers

%description triggers
Trigger script for Helix Swarm.

This package includes triggers to support Swarm that must be installed on the
same host as the Perforce Server (Swarm itself may be on a different host).

################################################################################
# Prepare building the packages
%prep

# Extract the top-level directory name from the tarfile
pwd
%define tarname %(tar -tf %{SOURCE0} | head -n 1 | cut -d / -f 1)
%setup -n %{tarname}
rm -rf ${RPM_BUILD_ROOT}

################################################################################
# Build files
%build

# Remove files we don't want in the package
pwd
rm -vf p4-bin/scripts/swarm-trigger.vbs
ls -dF p4-bin/bin.* | grep -v "linux26%{_arch}/" | xargs rm -rvf

# Create a sample trigger configuration file
sed -ne '/^# SWARM_HOST/,/^ADMIN_TICKET_FILE/p' %{SWARM_TRIG_SCRIPT_SRC} > %{SWARM_CONFIG_SRCNAME}

exit 0

################################################################################
# Install files to the package root
%install

# The following are specifically for helix-swarm-triggers
install -d                                  ${RPM_BUILD_ROOT}%{SWARM_TRIG_BINDIR}
install -m 755  %{SWARM_TRIG_SCRIPT_SRC}    ${RPM_BUILD_ROOT}%{SWARM_TRIG_BINDIR}

install -d                                  ${RPM_BUILD_ROOT}%{PERFORCE_CFGDIR}
mv              %{SWARM_CONFIG_SRCNAME}     ${RPM_BUILD_ROOT}%{SWARM_TRIG_CONFIG}

# The primary material (note we 'cp -r *' after the 'mv' above)
install -d                  ${RPM_BUILD_ROOT}%{SWARM_ROOT}
cp -r           *           ${RPM_BUILD_ROOT}%{SWARM_ROOT}

install -d                  ${RPM_BUILD_ROOT}%{SWARM_SBINDIR}
install         %{SOURCE1}  ${RPM_BUILD_ROOT}%{SWARM_SBINDIR}

install -d                  ${RPM_BUILD_ROOT}%{APACHE_CFGDIR}
install -m 644  %{SOURCE2}  ${RPM_BUILD_ROOT}%{APACHE_CFGDIR}

install -d                  ${RPM_BUILD_ROOT}%{CRON_DIR}
install -m 644  %{SOURCE3}  ${RPM_BUILD_ROOT}%{CRON_SCRIPT}

exit 0

################################################################################
# Check the installed files
#%check
#
#exit 0

################################################################################
# Files of each package
%files

# Perforce config directory
%dir %{PERFORCE_CFGDIR}

# Special handling for data directory
#%dir %attr(0700, %{APACHE_USER}, %{APACHE_GROUP}) %{SWARM_DATADIR}
%attr(0700, %{APACHE_USER}, %{APACHE_GROUP}) %{SWARM_DATADIR}
#%attr(0700, %{APACHE_USER}, %{APACHE_GROUP}) %{SWARM_DATADIR}/README.txt

# Core material
%{SWARM_ROOT}/index.html
%{SWARM_ROOT}/library
%{SWARM_ROOT}/module
%{SWARM_ROOT}/p4-bin
%{SWARM_ROOT}/public
%{SWARM_ROOT}/readme
%{SWARM_SBINDIR}
%{SWARM_ROOT}/Version

# Swarm vhost file
%config(noreplace) %{SWARM_VHOST}

# Swarm cron job
%{CRON_SCRIPT}

#=========#=========#=========#=========#=========#=========#=========#=========
%files optional

# this package installs no files

#=========#=========#=========#=========#=========#=========#=========#=========
%files triggers

%{SWARM_TRIG_BINDIR}
%config(noreplace) %{SWARM_TRIG_CONFIG}

################################################################################
# Pre-install scripts
%pre

if [ "$1" == "1" ]; then
    mode="install"
else
    mode="upgrade"
fi

# If we're upgrading and there's no Swarm cron config
if [ "$1" == "2" ] && [ ! -r "%{CRON_CONFIG}" ]; then
    echo "%{name}: Handling upgrade exception for Swarm 2014.2 cron job..."

    # If the previous cron script is there, obtain the swarm host used
    if [ -r "%{CRON_SCRIPT_OLD}" ]; then
        swarmhost=`sed -ne "/^[^#].*wget.*queue\/worker/s,.*https*://\([^/]*\).*,\1,p" "%{CRON_SCRIPT}"`
        echo "%{name}: -obtained Swarm hostname from prior cron job script: [$swarmhost]"
        mkdir -p `dirname %{CRON_CONFIG}`
        cat << __CRON_CONFIG__ > "%{CRON_CONFIG}"
# Swarm cron configuration
#
# Format (one per line):
# <swarm-host>[:<port>]
#
$swarmhost
__CRON_CONFIG__
        echo "%{name}: -wrote Swarm cron config [%{CRON_CONFIG}]"
    else
        echo "%{name}: -warning: Swarm cron script not found [%{CRON_SCRIPT_OLD}]"
    fi
fi

# Remove the obsoleted 'perforce-swarm' cronfile, if it exists
if [ -w "%{CRON_SCRIPT_OLD}" ]; then
    rm "%{CRON_SCRIPT_OLD}"
    echo "%{name}: -removed: Obsoleted cronfile [%{CRON_SCRIPT_OLD}]"
fi

exit 0

################################################################################
# Post-install scripts
%post

if [ "$1" == "1" ]; then
    mode="install"
else
    mode="upgrade"
fi

# Turn off SELinux enforcing mode
if [ "`/usr/sbin/getenforce | tr '[:upper:]' '[:lower:]'`" == "enforcing" ]; then
    echo "%{name}: Setting SELinux to permissive mode (with apologies to Dan Walsh)..."
    /usr/sbin/setenforce 0
fi

p4php_good=0
while true; do
    phpver=`php -v 2> /dev/null | sed -e '/^PHP/s/PHP \([0-9]\)\.\([0-9][0-9]*\).*/\1\2/;q'`
    if [ -z "$phpver" ]; then
        error_msg="could not parse version from 'php -v'"
        break
    fi

    # Check if a variant of P4PHP is available
    osplat=`uname -m | sed -e 's/i.86/x86/'`
    p4php_variant="%{SWARM_ROOT}/p4-bin/bin.linux26${osplat}/perforce-php${phpver}.so"
    if [ ! -s "${p4php_variant}" ]; then
        error_msg="invalid P4PHP variant [$p4php_variant] for [`php -v 2> /dev/null | head -n1 | awk '{print $1,$2}'`]"
        break
    fi

    if [ ! -d "%{PHPINI_DIR}" ]; then
        error_msg="invalid PHP configuration directory [%{PHPINI_DIR}]"
        break
    fi

    tmpp4phpini="%{P4PHP_INI}.$$"
    cat << __P4PHP_INI__ > "$tmpp4phpini"
; P4PHP Extension (for Perforce Swarm)
extension=${p4php_variant}
__P4PHP_INI__

    # Put new file into place only if one doesn't exist
    if [ -r "%{P4PHP_INI}" ]; then
        # Check if it's the same
        if diff -q "$tmpp4phpini" "%{P4PHP_INI}" > /dev/null; then
            # No difference, so remove what we created
            rm -f "$tmpp4phpini"
        else
            # Different, so move it to .rpmnew
            mv "$tmpp4phpini" "%{P4PHP_INI}.rpmnew"
            echo "warning: %{P4PHP_INI} created as %{P4PHP_INI}.rpmnew"
         fi
    else
        # Move into place
        mv "$tmpp4phpini" "%{P4PHP_INI}"
    fi

    p4php_good=1
    break
done

if [ $p4php_good -ne 1 ]; then
    cat << __P4PHP_MSG__

!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
!!
!! %{name}: Warning: Trouble configuring P4PHP
!! Problem:
!! $error_msg
!!
!! Swarm cannot function without P4PHP; please investigate and rectify.
!!
!! To manually configure P4PHP, add a line to your php.ini with:
!! extension=%{SWARM_ROOT}/p4-bin/bin.<plat>/perforce-php<ver>.so
!!
!! You can confirm it works by running:
!! $ php --ri perforce
!!
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

__P4PHP_MSG__
fi

# Suppress this message if the config file is already present
if [ "$mode" == "install" ] && [ ! -e "%{SWARM_CONFIG}" ]; then
    cat << __POST_INSTALL_MSG__

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::  Swarm is now installed, but not yet configured.
::  You must run the following to configure Swarm (as root):
::
::      %{SWARM_SBINDIR}/configure-swarm.sh
::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

__POST_INSTALL_MSG__

elif [ "$mode" == "upgrade" ]; then
    # Restart Apache to kill old workers
    echo "%{name}: restarting Apache to update Swarm workers..."
    apachectl restart
    cat << __POST_UPGRADE_MSG__

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::  Swarm has been upgraded, using the existing config.
::
::  If you wish to change any settings, you can run the following to
::  reconfigure Swarm (as root):
::
::      %{SWARM_SBINDIR}/configure-swarm.sh
::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

__POST_UPGRADE_MSG__
fi

exit 0

#=========#=========#=========#=========#=========#=========#=========#=========
%post optional

if [ "$1" == "1" ]; then
    mode="install"
else
    mode="upgrade"
fi

if [ "$mode" == "install" ]; then
    echo "%{name}-optional: restarting Apache to ensure PHP Imagick extension is loaded..."
    apachectl restart
fi

exit 0

#=========#=========#=========#=========#=========#=========#=========#=========
%post triggers

if [ "$1" == "1" ]; then
    mode="install"
else
    mode="upgrade"
fi

if [ "$mode" == "install" ]; then
    cat << __POST_INSTALL_MSG__

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::  Swarm Triggers are now installed, but not configured.
::  You must edit the following file as appropriate:
::
::      %{SWARM_TRIG_CONFIG}
::
::  Ensure you create the triggers in your Perforce server.
::
::  Reference:
::  http://www.perforce.com/perforce/doc.current/manuals/swarm/setup.perforce.html
::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

__POST_INSTALL_MSG__
fi

exit 0

################################################################################
# Post-uninstall scripts (note: yum squelches output from %postun)
%postun

if [ "$1" == "0" ]; then
    mode="uninstall"
else
    mode="upgrade"
fi

if [ "$mode" == "uninstall" ]; then
    echo "warning: %{P4PHP_INI} saved as %{P4PHP_INI}.rpmsave"
    mv "%{P4PHP_INI}" "%{P4PHP_INI}.rpmsave"
    echo "%{name}: restarting Apache to stop Swarm workers and unload P4PHP..."
    apachectl restart
fi

exit 0

################################################################################
# Post-transaction script (only runs for install and upgrade)
%posttrans

# Correct a bug in 2014.3's postun script that disabled P4PHP on upgrade
if [ -a "%{P4PHP_INI}.rpmsave" ] && [ ! -a "%{P4PHP_INI}" ]; then
    mv "%{P4PHP_INI}.rpmsave" "%{P4PHP_INI}"
fi

exit 0

################################################################################
# The changelog (empty)
%changelog
