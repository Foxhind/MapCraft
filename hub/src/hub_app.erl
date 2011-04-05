%% @author Mochi Media <dev@mochimedia.com>
%% @copyright hub Mochi Media <dev@mochimedia.com>

%% @doc Callbacks for the hub application.

-module(hub_app).
-author("Mochi Media <dev@mochimedia.com>").

-behaviour(application).
-export([start/2,stop/1]).


%% @spec start(_Type, _StartArgs) -> ServerRet
%% @doc application start callback for hub.
start(_Type, _StartArgs) ->
    hub_deps:ensure(),
    hub_sup:start_link().

%% @spec stop(_State) -> ServerRet
%% @doc application stop callback for hub.
stop(_State) ->
    ok.
