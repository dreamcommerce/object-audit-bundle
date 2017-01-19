#!/usr/bin/env bash

CONTAINER=$1
shift

PARAMS=$(printf '%s ' ${@})

cd `dirname $0` && docker-compose run --rm $CONTAINER /docker/entry.sh $UID $PARAMS