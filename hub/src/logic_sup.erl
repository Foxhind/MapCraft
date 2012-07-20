%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.

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
