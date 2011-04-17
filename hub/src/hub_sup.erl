%% @author Mochi Media <dev@mochimedia.com>
%% @copyright 2010 Mochi Media <dev@mochimedia.com>

%% @doc Supervisor for the hub application.

-module(hub_sup).
-author("Mochi Media <dev@mochimedia.com>").

-behaviour(supervisor).

%% External exports
-export([start_link/0, upgrade/0]).

%% supervisor callbacks
-export([init/1]).

%% @spec start_link() -> ServerRet
%% @doc API for starting the supervisor.
start_link() ->
    supervisor:start_link({local, ?MODULE}, ?MODULE, []).

%% @spec upgrade() -> ok
%% @doc Add processes if necessary.
upgrade() ->
    {ok, {_, Specs}} = init([]),

    Old = sets:from_list(
            [Name || {Name, _, _, _} <- supervisor:which_children(?MODULE)]),
    New = sets:from_list([Name || {Name, _, _, _, _, _} <- Specs]),
    Kill = sets:subtract(Old, New),

    sets:fold(fun (Id, ok) ->
                      supervisor:terminate_child(?MODULE, Id),
                      supervisor:delete_child(?MODULE, Id),
                      ok
              end, ok, Kill),

    [supervisor:start_child(?MODULE, Spec) || Spec <- Specs],
    ok.

%% @spec init([]) -> SupervisorTree
%% @doc supervisor callback.
init([]) ->
    Processes = stats_specs() ++ mqueue_specs() ++ web_specs() ++ logic_procs() ++ pie_procs(),
    Strategy = {one_for_one, 10, 10},
    {ok, {Strategy, lists:flatten(Processes)}}.

%
% Private
%
web_specs() ->
    WebConfig = [{ip, config:get(listen)},
                 {port, config:get(port)},
                 {acceptor_pool_size, config:get(http_pool_size)} ],
    [ {hub_web,
	   {hub_web, start, [WebConfig]},
	   permanent, 5000, worker, dynamic}].

logic_procs() ->
	[ {logic, {logic, start_link, []},
	   permanent, brutal_kill, worker, [logic]},
	  sup(logic_sup, [])].

pie_procs() ->
	[ sup(pie_sup, []) ].

stats_specs() ->
	[ {stats, {stats, start_link, []},
	   permanent, brutal_kill, worker, [stats]} ].

mqueue_specs() ->
	[ {mqueue, {mqueue, start_link, []},
	   permanent, brutal_kill, worker, [mqueue]} ].

sup(Mod, Args) ->
	{Mod,
	 {Mod, start_link, Args},
	  permanent, 10000, supervisor, [Mod]}.

