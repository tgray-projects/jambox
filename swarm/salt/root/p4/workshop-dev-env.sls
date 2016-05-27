
swarm-packages:
  pkg.installed:
    - pkgs:
        - perforce-cli
        - perforce-server
        - apache2
        - libapache2-mod-php5
        - php-apc
        - php5-cli
        - php5-json
        - wget

# remove default vhost in Debian/Ubuntu
/etc/apache2/sites-enabled/000-default:
  file.absent: []

# ensure the apache server is running
apache2:
  service.running:
    - enable: True

# setup-p4d-for-swarm:
#   cmd.script:
#     - source: salt://p4/files/workshop-dev-env.sh
#     - creates: /opt/perforce/swarm/data/config.php
#     - creates: /opt/perforce/swarm/data/queue/tokens/00000000-0000-0000-0000-000000000000
## - creates: /etc/perforce/p4dctl.conf.d/workshop-dev.conf


# create_data_dir:
#   cmd.run:
#     - name: "mkdir -p /var/www/swarm/data; chown -R www-data: /var/www/swarm/data"
#     - creates: /var/www/swarm/data
