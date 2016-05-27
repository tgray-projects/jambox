***** Installation of Behat using composer: *****

Step 1: Run the following comamnd under the behat root directory:
$ curl http://getcomposer.org/installer | php
$ php composer.phar install

The above commands will create a 'vendor' directory that contains source files pulled in by the composer needed by the behat test framework

Step 2: Add the following to the Swarm Apache config file

<IfModule mod_setenvif.c>
    SetEnvIf Cookie "(^| )SwarmDataPath=((?:[0-9][a-z0-9\-]+))($|;)" SWARM_DATA_PATH=<PATH_TO_BEHAT_DATA_DIRECTORY>/$2
</IfModule>

Replacing the <PATH_TO_BEHAT_DATA_DIRECTORY> with the base directory of your behat data directory
e.g. /Users/elliotwiltshire/Perforce/ewiltshire_macmini/main/swarm/tests/behat/data

Step 3: If you are using Ubuntu, you may have to add the following line to <path to php>/cli/php.ini

    extension=<Path to swarm>/p4-bin/bin.<OS type>/perforce-php<php version>.so

Step 4: Restart the Apache process (sudo apachectl -k restart).

Step 5: Set up a Selenium2 Session
        - Download latest Selenium jar from the: http://seleniumhq.org/download/
        - Run Selenium2 jar before your test suites (you can start this proxy during system startup):
            - $ java -jar selenium-server-*.jar
        - For more information see http://docs.behat.org/en/v2.5/cookbook/behat_and_mink.html

Step 6: Copy config/behat.yml.template to config/behat.yml and replace the 'base_url' field with the local machine's Swarm host url
        e.g. http://swarm.porus.perforce.ca or http://localhost
        - The 'base_url' path must start with "http://"; just IP or localhost will not suffice

Step 7: Run the following command from the 'behat' project root. The tests should run successfully.
        e.g. behat $ bin/behat -p=firefox_p4d10.2 features/
		- For more detailed output use --vebose and/or --expand


 Alternatively to steps 6 & 7 , the run_behat.sh script can be used as:
        behat $ run_behat.sh -b <browser> -p <p4dver> [-s <swarmhost>] [-u] [-v]

        where,
            -b: specify browser: ( default firefox)
            -p: specify p4d version
            -s: specify swarm host (default is localhost)
            -u: update the composer.phar file
            -v: verbose behat console output


Note:
- All config files will be stored under 'config' dir
- All screenshots, swarm & p4d logs for failing tests will be stored under 'failures' dir
- The Swarm data directories for the individual tests will be stored under 'data' dir.
- The individual test data directories and p4d logs will be cleaned up at the end of each scenario,
    unless the documentation line '@AfterScenario' in features/bootstrap/P4Context::teardown() is commented out
- If the data of a test run is not cleaned up the scenario can be loaded in the browser by going to
    the localhost swarm instance and adding a cookie with the name "SwarmDataPath" and setting the
    value to the UUID of the test that you want to look at.
    - The UUID of the test can be found in the data/<test folder>/script-triggers.sh
		- The <test folder> is also the UUID
    - Use a tool such as EditThisCookie(Chrome) or Cookies Manager+(Firefox) to set the cookie
- Currently the behat.yml file is not version controlled this causes a couple of things:
	- The behat.yml must be updated any time there is a change to the behat.yml.template
	- Any changes made to the behat.yml must also be made to the behat.yml.template
