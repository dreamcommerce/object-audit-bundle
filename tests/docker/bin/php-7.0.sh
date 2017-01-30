#!/usr/bin/env bash

cd `dirname $0` && docker-compose run --rm php-7.0 /docker/entry.sh $UID php "$@"