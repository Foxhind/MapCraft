-module(pie_sup).
-behaviour(supervisor).

-export([start_link/0]).
-export([init/1]).

start_link() ->
	supervisor:start_link({local, ?MODULE}, ?MODULE, []).

%%
%% supervisor callbacks
%%

init(_Options) ->
	{ok, {{simple_one_for_one, 5, 60},
		  [{pie, {pie, start_link, []},
			transient, 5000, worker, [pie]}]
		 }}.
