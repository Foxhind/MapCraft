-module(logic_worker).
-behaviour(gen_server).

-export([start_link/0]).
-export([execute/2]).
-export([init/1, handle_call/3, handle_cast/2]).

start_link() ->
	gen_server:start_link(logic_worker, [], []).

-record(state, {id}).

%% ------- Interface ---------

execute(Worker, _Task) ->
	gen_server:call(Worker, sleep).

%% ------- gen_server callbacks ----

init(_Options) ->
	Id = stats:incr(logic_starts),
	State = #state{id = Id},
	logic:add_me(),
	{ok, State}.

handle_call(sleep, _From, State) ->
	io:format("Worker ~p going to sleep ~n", [self()]),
	gen_server:cast(self(), {awake, 10000}),
	{reply, ok, State}.

handle_cast({awake, T}, State) ->
	receive
	after T ->
			io:format("Worker ~p is awake ~n", [self()]),
			logic:add_me()
	end,
	{noreply, State}.
