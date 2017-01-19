#!/usr/bin/env bash

ENTRY_UID=$1
shift

PARAMS=$(printf '%s ' ${@})

export COMPOSER_HOME=/docker/.composer

adduser --disabled-password --gecos '' --uid $ENTRY_UID --home /home/docker docker >/dev/null
adduser docker sudo >/dev/null
ln -s /docker/.composer /home/docker/.composer >/dev/null
ln -s /docker/.ssh /home/docker/.ssh >/dev/null
su -m docker -c "export PATH=$PATH:/docker/app/bin && $PARAMS"