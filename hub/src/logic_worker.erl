-module(logic_worker).
-behaviour(gen_server).

-export([start_link/0]).
-export([process/2]).
-export([init/1, handle_cast/2]).

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
	Id = stats:incr(logic_starts),
	Port = open_port({spawn, get_logic_cmd(Id)}, [stream, {line, 1000}]),
	logic:add_me(),
	{ok, #state{id = Id, port = Port}}.


handle_cast({process, HubReq}, State) ->
	{ok, Res} = process_req(State#state.port, HubReq#hub_req.cmd),
	router:spawn_new(HubReq, Res),
	logic:add_me(),
	{noreply, State}.

%% TODO: trapexit for Port, handle terminate (close port)

%%
%% private
%%

get_logic_cmd(Id) ->
	"priv/logic.sh -i " ++ integer_to_list(Id).

get_timeout() ->
	1000.

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

	after get_timeout() ->
			timeout
	end.

process_req(Port, Cmd) ->
	port_command(Port, Cmd),
	read_response(Port, [], []).
