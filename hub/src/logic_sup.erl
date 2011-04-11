-module(logic_sup).
-behaviour(supervisor).

-export([start_link/0]).
-export([init/1]).

start_link() ->
	supervisor:start_link({local, ?MODULE}, ?MODULE, []).

init(_Args) ->
	Workers = initial_workers_specs(),
	Strategy = {one_for_one, 5, 60},
	{ok, {Strategy, Workers}}.

initial_workers_specs() ->
	{Min, _} = config:get(logic_pool_size),
	[worker_spec(I) || I <- lists:seq(1,Min)].

worker_spec(Index) ->
	Id = list_to_atom("logic_worker" ++ integer_to_list(Index)),
	{Id, {logic_worker, start_link, []},
	 permanent, 5000, worker, [logic_worker]}.
