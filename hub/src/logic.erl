-module(logic).
-behaviour(gen_server).

-export([start_link/0]).
-export([add_me/0, process/1]).
-export([init/1, handle_call/3]).

start_link() ->
	gen_server:start_link({local, logic}, logic, [], []).

%%
%% Interface
%%

add_me() ->
	gen_server:call(logic, {add_worker, self()}).

process(Task) ->
	case gen_server:call(logic, {process, Task}) of
		wait ->
			error_logger:warning_report(["all logic workers are busy. Please increase logic workers count",
										 {self, self()},
										 {task, Task}]),
			receive
			after 1000 ->
					process(Task)
			end;
		Res ->
			Res
	end.

%% ------- gen_server callbacks ----

init(Options) ->
	{ok, _Pool = []}.

handle_call({add_worker, Worker}, _From, Pool) ->
	{reply, ok, [Worker|Pool]};

handle_call({process, Task}, _From, Pool) ->
	case Pool of
		[] ->
			{reply, wait, Pool};
		[Head|Tail] ->
			logic_worker:process(Head, Task),
			{reply, ok, Tail}
	end.
