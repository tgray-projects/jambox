# ref: http://docs.saltstack.com/en/latest/ref/states/all/salt.states.pkg.html#salt.states.pkg.uptodate
p4repo:
  pkgrepo.managed:
    - humanname: Perforce Nightly Packages
    - name: deb http://package.perforce.com/apt/ubuntu precise release
    - file: /etc/apt/sources.list.d/perforce.list
    # - require_in:
    #   - pkg: perforce-cli
  cmd.run:
    - name:  wget -q http://package.perforce.com/perforce.pubkey -O - | apt-key add -
    - unless: apt-key list | grep Perforce
