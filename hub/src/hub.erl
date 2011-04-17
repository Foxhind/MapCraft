%% @author Mochi Media <dev@mochimedia.com>
%% @copyright 2010 Mochi Media <dev@mochimedia.com>

%% @doc hub.

-module(hub).
-author("Mochi Media <dev@mochimedia.com>").
-export([start/0, stop/0]).

ensure_started(App) ->
    case application:start(App) of
        ok ->
            ok;
        {error, {already_started, App}} ->
            ok
    end.


%% @spec start() -> ok
%% @doc Start the hub server.
start() ->
    hub_deps:ensure(),
    ensure_started(crypto),
    ensure_started(gproc),
    application:start(hub).


%% @spec stop() -> ok
%% @doc Stop the hub server.
stop() ->
    application:stop(hub).
