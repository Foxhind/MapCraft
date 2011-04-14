-module(logic).
-behaviour(gen_server).

-export([start_link/0]).
-export([add_me/0, process/1, process_async/1]).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

start_link() ->
	gen_server:start_link({local, logic}, logic, [], []).

%%
%% Interface
%%

add_me() ->
	gen_server:call(logic, {add_worker, self()}).

process(HubReq) ->
	case gen_server:call(logic, {process, HubReq}) of
		wait ->
			error_logger:warning_report(["all logic workers are busy. Please increase logic workers count",
										 {self, self()},
										 {hubreq, HubReq}]),
			receive
			after 1000 ->
					process(HubReq)
			end;
		Res ->
			Res
	end.

process_async(HubReq) ->
	Fun = fun() ->
				  ok = logic:process(HubReq)
		  end,
	spawn(Fun),
	ok.

%%
%% gen_server callbacks
%%
init(_Args) ->
	{ok, _Pool = []}.

handle_call({add_worker, Worker}, _From, Pool) ->
	{reply, ok, [Worker|Pool]};

handle_call({process, HubReq}, _From, Pool) ->
	case Pool of
		[] ->
			{reply, wait, Pool};
		[Head|Tail] ->
			logic_worker:process(Head, HubReq),
			{reply, ok, Tail}
	end.

handle_info(_Msg, State) ->
	{noreply, State}.

handle_cast(_Msg, State) ->
	{noreply, State}.

code_change(_, State, _) ->
	{ok, State}.

terminate(_Reason, _State) ->
	ok.
