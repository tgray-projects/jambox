#!/bin/bash
# Run script to create a docker image with swarm and p4d installed

# Find out if the docker service is already enabled - configure and start if not
systemctl is-enabled docker.service
if [ $? -eq 0 ]; then
    echo "Docker already installed"
else
    echo "Configuring Docker"
    usermod -a -G docker perforce && echo "Docker Group Created"
    systemctl enable docker.service 
    systemctl start docker.service && echo "Docker Service Configured"
fi

# Get p4d/p4 and set permissions if necessary
for binary in p4 p4d
do
    if [ -r "$binary" ]; then
        echo "$binary already exists."
    else
        echo "Downloading $binary..."
        wget -q "http://ftp.perforce.com/perforce/r13.3/bin.linux26x86_64/$binary"
    fi
    chmod 755 "$binary"
done

# Building Docker image based on Dockerfile located in same dir
docker build -t swarm-deb-test .

# Clean up
docker ps -a -q | xargs -rn 1 docker stop || true
docker ps -a -q | xargs -rn 1 docker rm > /dev/null 2>&1 || true
docker rmi swarm-deb-test
docker images | grep none | awk '{print $$3}' | xargs -rn 1 docker rmi || true



