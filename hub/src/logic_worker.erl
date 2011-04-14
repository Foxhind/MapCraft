-module(logic_worker).
-behaviour(gen_server).

-export([start_link/0]).
-export([process/2]).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

-include("hub.hrl").
-record(state, {id, port}).

start_link() ->
	gen_server:start_link(logic_worker, [], []).


%%
%%  Interface
%%
process(Worker, HubReq) ->
	gen_server:cast(Worker, {process, HubReq}).

%%
%% gen_server callbacks
%%
init(_Options) ->
	Id = stats:get_next({logic, starts}),
	Cmd = config:get(logic_cmd) ++ " -i " ++ integer_to_list(Id),
	Port = open_port({spawn, Cmd}, [stream, {line, 1000}, exit_status]),
	logic:add_me(),
	{ok, #state{id = Id, port = Port}}.


handle_cast({process, HubReq}, State) ->
	stats:incr({logic, requests}),
	hub_web:follow_me(HubReq),
	{ok, Res} = process_req(State#state.port, HubReq#hub_req.cmd),
	router:spawn_new(HubReq, Res),
	logic:add_me(),
	{noreply, State}.

handle_info({Port, {exit_status, Reason}}, #state{port = Port} = State) ->
	stats:incr({logic, crashes}),
	error_logger:error_report(["Logic process terminated", {state, State}, {retcode, Reason}]),
	{stop, {port_terminated, Reason}, State}.

terminate({port_terminated, _}, _) ->
	ok;
terminate(_Reason, #state{port = Port}) ->
	port_close(Port).

handle_call(_Msg, _From, State) ->
	{noreply, State}.

code_change(_, State, _) ->
	{ok, State}.

%% TODO: trapexit for Port, handle terminate (close port)

%%
%% private
%%

%% We are recieving response lines splitted by 1000 bytes.
%% join them and return all lines
read_response(Port, RespAcc, LineAcc) ->
	receive
		{Port, {data, {eol, "EOR"}}} ->
			{ok, RespAcc};

		{Port, {data, {eol, Line}}} ->
			Response = lists:flatten(lists:reverse([Line | LineAcc])),
			read_response(Port, [Response | RespAcc], []);

		{Port, {data, {noeol, LinePart}}} ->
			read_response(Port, RespAcc, [LinePart | LineAcc])

	after config:get(logic_timeout) * 1000 ->
			timeout
	end.

process_req(Port, Cmd) ->
	port_command(Port, Cmd),
	read_response(Port, [], []).
