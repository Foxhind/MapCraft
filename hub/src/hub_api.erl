-module(hub_api).

-export([handle/3]).


handle('GET', Req, ["stats"]) ->
	hub_web:ok(Req, stats:fdump()).
