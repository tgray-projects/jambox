#!/bin/bash
# This script is run by jenkins to ensure the test environment is set up properly
# before the tests are run.
#
# Task List:
# Set environment variables.
# Create the base data directory.
# Copy the base config file into the data directory.
# Modify .htaccess for test redirections; note that this change cannot be
#     committed as it breaks subdirectory installations of Swarm.
# Execute tests.

export SAUSAGE_PATH=/home/perforce/sausage
export P4D_BINARY=/server/p4d
export SWARM_TEST_HOST=frontend.swarm.perforce.ca

if [ ! -d "/home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/data/" ]; then
  mkdir /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/data/
fi

if [ ! -f "/home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/data/config.php" ]; then
  cp /home/perforce/main_config.php /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/data/config.php
fi

chmod +w /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/public/.htaccess
# must run through temp file
sed '42iRewriteBase /' /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/public/.htaccess > /tmp/htaccess
mv /tmp/htaccess /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/public/.htaccess

/home/perforce/sausage/vendor/bin/phpunit -c /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/tests/phpunit/FrontendTest/tests/phpunit.xml /home/perforce/jenkins/workspace/swarm-main-regression-test-frontend/tests/phpunit/FrontendTest/tests/