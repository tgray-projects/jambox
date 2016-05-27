Installing JamBox on a Raspberry Pi
===================================

1.) On your Raspberry Pi, run (instructions were tested on a Raspberry Pi 2 running Raspbian Jessie):

`sudo apt-get update`    
`sudo apt-get install -y build-essential`   
`sudo apt-get install -y libapache2-mod-php5`   
`sudo apt-get install -y php5-dev`   

2.) Get the Perforce client, server, C++ API, PHP API:

`wget ftp://ftp.perforce.com/perforce/r15.2/bin.linux26armhf/p4`   
`wget ftp://ftp.perforce.com/perforce/r15.2/bin.linux26armhf/p4d`   
`wget ftp://ftp.perforce.com/perforce/r15.2/bin.linux26armhf/p4api.tgz`   
`wget -O p4php.zip https://swarm.workshop.perforce.com/projects/perforce-software-p4php/archives/main.zip`   

3.) Change permissions on p4 and p4d and move files to bin directory:  

`chmod +x p4`   
`chmod +x p4d`   
`sudo mv p4 /usr/local/bin`   
`sudo mv p4d /usr/local/bin`   

4.) Start p4d,  create 'guest' and '.swarm' depot:

`mkdir /home/pi/p4root`    
`p4d -r /home/pi/p4root -p 0.0.0.0:1666 -d`        
`p4 -p localhost:1666 depot guest`    
`p4 -p localhost:1666 depot .swarm`    

5.) Set configurables to enable [DVCS](https://www.perforce.com/blog/150402/now-available-helix-versioning-engine-dvcs-capabilities) functionality:

`p4 configure set server.allowfetch=2`    
`p4 configure set server.allowpush=2`    

6.) Build P4PHP:

`tar zxvf p4api.tgz`    
`unzip p4php.zip`    
`mv main p4php`    
`cd p4php`    
`phpize`     
`./configure --with-perforce=../p4api-2015.2.1326881 (your version might be different)`    
`make`    
`make test`    
`sudo make install`

**NOTE**:
In the summary, you should see something like:   
Libraries have been installed in:    
   /home/pi/p4php/modules

7.) Add this line to your php.ini file:  
extensions=/home/pi/p4php/modules/perforce.so   

**NOTE**: If you've been following the directions, php.ini file should be located here: /etc/php5/apache2/php.ini

8.) Download JamBox code and setup:   

`p4 -d jambox -u guest clone -p workshop.perforce.com:1666 -f //guest/thomas_gray/jambox/main/...`    
`cd jambox/swarm`    
`sudo chown www-data data`    

9.) Add this config.php file to $JAMBOX_HOME/data:

>     <?php    
>         return array(    
>             'p4' => array(    
>                  'port'     => 'localhost:1666',    
>                  'user'     => 'swarm',    
>                  'password' => 'password',    
>              ),    
>              'p4_super' => array(    
>                  'port'     => 'localhost:1666',    
>                  'user'     => 'perforce',    
>                  'password' => 'password',    
>              ),
>              'security' => array(
>                  'require_login' => false,
>                  'disable_autojoin' => true,
>              ),
>              'notifications' => array(
>                  'honor_p4_reviews' => true,
>              ),
>              'queue'  => array(
>                 'workers'             => 3,    // defaults to 3
>                 'worker_lifetime'     => 595,  // defaults to 10 minutes (less 5 seconds)
>                 'worker_task_timeout' => 1800, // defaults  30 minutes
>                 'worker_memory_limit' => '1G', // defaults to 1 gigabyte
>              ),
>              'reviews' => array(
>                  'disable_commit' => false,
>              ),
>          );

**NOTE: Make sure you have created a 'swarm' and 'perforce' user with super permissions (p4 user -f username)**

10.) Setup Swarm Triggers:   

`/home/pi/jambox/swarm/p4-bin/scripts/swarm-triggers.sh -o > swarm-triggers.txt`    
`p4 -p localhost:1666 triggers -o > default-triggers.txt`    
`cat default-triggers.txt swarm-triggers.txt > final.txt`    
`p4 -p localhost:1666 triggers -i < final.txt`      

11.) In the /etc/apache2/sites-available directory, edit the file '000-default.conf' with following contents:    
>     <Virtualhost *:80>     
>         ServerName localhost    
>         ErrorLog "/var/log/apache2/swarm_error.log"    
>         CustomLog "/var/log/apache2/swarm_access.log" common
>         DocumentRoot "/home/pi/jambox/swarm/public"
>         <Directory "/home/pi/jambox/swarm/public">
>             AllowOverride All
>             Require all granted
>         </Directory>
>     </VirtualHost>

12.) Restart Apache

`sudo apachectl restart`    

Let's try logging into your Swarm instance:
Open up a browser and enter 127.0.0.1:80  (or ip address if accessing from another machine)
Log into using username: pi (leave password blank)

Now that's you've successfully logged in, there are few more things we need to do.

13.) Setup the Trigger Token:
* Click on 'pi' userid (upper right corner) | About Swarm      
* Copy Trigger Token   
* Open up the file: /home/pi/jambox/swarm/p4-bin/scripts/swarm-triggers.sh  
* Fill in the values for SWARM_HOST and SWARM_TOKEN:      
   SWARM_HOST="http://ip_address/"    
   SWARM_TOKEN="Trigger Token you just copied"   

14.) Add Workers:   

`crontab -e`

Add the following line and exit:    
`* * * * * wget -q -O /dev/null -T5 http://IP_ADDRESS/queue/worker`    
