#!/usr/bin/env bash

cd `dirname $0` && docker-compose run --rm php-7.1 /docker/entry.sh $UID php "$@"
