# Notes:
#
# 1. Need the swarm version in here to be able to install in a
#    version-specific path. Means this file needs to be edited
#    as part of the build.
#
# 2. If the swarm version came from Jam, that would also help
#    with the BUILD_ARCH hack below.
#
# 3. Also it would help if swarm-package.tgz was provided by Jam
#
INSTALL=install

PERFORCE_ROOT=/opt/perforce
PERFORCE_CFG_DIR=$(PERFORCE_ROOT)/etc
SWARM_TRIGGER_BIN_DIR=$(PERFORCE_ROOT)/swarm-triggers/bin
SWARM_ROOT_DIR=$(PERFORCE_ROOT)/swarm
SWARM_DATA_DIR=$(SWARM_ROOT_DIR)/data
SWARM_SBIN_DIR=$(SWARM_ROOT_DIR)/sbin
SWARM_SITE_DIR=$(SWARM_ROOT_DIR)/public
APACHE_SITES_DIR=/etc/apache2/sites-available

# The same paths, under the build tree
DEST_PERFORCE_CFG_DIR=$(DESTDIR)$(PERFORCE_CFG_DIR)
DEST_SWARM_TRIGGER_BIN_DIR=$(DESTDIR)$(SWARM_TRIGGER_BIN_DIR)
DEST_SWARM_ROOT_DIR=$(DESTDIR)$(SWARM_ROOT_DIR)
DEST_SWARM_DATA_DIR=$(DESTDIR)$(SWARM_DATA_DIR)
DEST_SWARM_SBIN_DIR=$(DESTDIR)$(SWARM_SBIN_DIR)
DEST_SWARM_SITE_DIR=$(DESTDIR)$(SWARM_SITE_DIR)
DEST_APACHE_SITES_DIR=$(DESTDIR)$(APACHE_SITES_DIR)

# Our build architecture, so we know which libs to eliminate
BUILD_ARCH=`echo ${DEB_BUILD_ARCH} | sed -e 's/i386/x86/;s/amd64/x86_64/'`

build: swarm.tgz

install: 
	rm -rf swarm-*
	$(INSTALL) -dv $(DEST_PERFORCE_CFG_DIR)
	$(INSTALL) -dv $(DEST_SWARM_SBIN_DIR)
	$(INSTALL) -dv $(DEST_SWARM_TRIGGER_BIN_DIR)
	$(INSTALL) -dv $(DEST_APACHE_SITES_DIR)

	tar -zxf swarm.tgz
	mv -v swarm-*/* $(DEST_SWARM_ROOT_DIR)

	# Remove p4php .so files for non-target platforms. This 
	# prevents an error when dh_shlibdeps tries to analyse these
	# libraries and can't understand them. This is pretty ugly,
	# but a better solution eludes me for now.
	cd $(DEST_SWARM_ROOT_DIR)/p4-bin && ls -dF bin.* | grep -v "linux26$(BUILD_ARCH)/" | xargs rm -rvf

	$(INSTALL) -m 644 perforce-swarm-site.conf $(DEST_APACHE_SITES_DIR)
	$(INSTALL) -m 755 configure-swarm.sh $(DEST_SWARM_SBIN_DIR)

	# Copy the trigger to where it should live for the stand-alone trigger package
	$(INSTALL) $(DEST_SWARM_ROOT_DIR)/p4-bin/scripts/swarm-trigger.pl $(DEST_SWARM_TRIGGER_BIN_DIR)

