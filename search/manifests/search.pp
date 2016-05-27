# we will build p4-search else where and just deploy the war as opposed to building
# on the final machine

package { "openjdk-7-jdk":
	ensure => present
}

file {"/opt/gradle":
    ensure => directory
}
