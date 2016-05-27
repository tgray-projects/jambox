/etc/apt/sources.list:
  file.managed:
    - source: salt://apt/files/sources.list
    - mode: 444
    - user: root
    - group: root
    - template: jinja
    - context:
      codename: {{ grains['lsb_distrib_codename'] }}


# Local Variables:
# indent-tabs-mode: nil
# End: