#!/bin/sh
cd $(dirname $0)
exec erl -pa ebin edit deps/*/ebin "$@" -boot start_sasl \
    -sname hub_dev \
    -s hub \
    -s reloader
