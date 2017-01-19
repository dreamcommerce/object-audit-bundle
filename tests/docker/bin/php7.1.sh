#!/usr/bin/env bash

cd `dirname $0` && docker-compose run --rm php7.1 /docker/entry.sh $UID php "$@"
