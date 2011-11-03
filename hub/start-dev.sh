#!/bin/sh
cd $(dirname $0)

PID_FILE=${1:?Pass pid file path as first arg}
shift
echo $$ >$PID_FILE

exec erl -pa ebin edit deps/*/ebin -boot start_sasl \
    -sname hub_dev \
    -s hub \
    -s reloader \
    "$@"
