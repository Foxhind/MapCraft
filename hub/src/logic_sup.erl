-module(logic_sup).
-behaviour(supervisor).

-export([start_link/0]).
-export([init/1]).

start_link() ->
	supervisor:start_link({local, ?MODULE}, ?MODULE, []).

init(_Args) ->
	Workers = generate_workers(4),
	Strategy = {one_for_one, 5, 60},
	{ok, {Strategy, Workers}}.

generate_workers(N) ->
	[worker_spec(I) || I <- lists:seq(1,N)].

worker_spec(Index) ->
	Id = list_to_atom("logic_worker" ++ integer_to_list(Index)),
	{Id, {logic_worker, start_link, []},
	 permanent, 5000, worker, [logic_worker]}.
