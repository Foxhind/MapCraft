-module(logic_worker).
-behaviour(gen_server).

-export([start_link/0]).
-export([process/2, sleep/1]).
-export([init/1, handle_cast/2]).

start_link() ->
	gen_server:start_link(logic_worker, [], []).

-record(state, {id, port}).

%%
%%  Interface
%%
process(Worker, Cmd) ->
	{ok, Cmd2} = sanitize_cmd(Cmd),
	gen_server:cast(Worker, {process, Cmd2}).

sleep(Worker) ->
	gen_server:cast(Worker, sleep).

%%
%% gen_server callbacks
%%
init(_Options) ->
	Id = stats:incr(logic_starts),
	Port = open_port({spawn, get_logic_cmd(Id)}, [stream, {line, 1000}]),
	logic:add_me(),
	{ok, #state{id = Id, port = Port}}.


handle_cast(sleep, State) ->
	io:format("Worker ~p going to sleep ~n", [self()]),
	gen_server:cast(self(), {awake, 10000}),
	{noreply, State};

handle_cast({process, Cmd}, State) ->
	{ok, Res} = process_cmd(State#state.port, Cmd),
	io:format("Result of worker: ~p~n", [Res]),
	logic:add_me(),
	{noreply, State};

handle_cast({awake, T}, State) ->
	receive
	after T ->
			io:format("Worker ~p is awake ~n", [self()]),
			logic:add_me()
	end,
	{noreply, State}.

%% TODO: trapexit for Port, handle terminate (close port)

%%
%% private
%%

get_logic_cmd(Id) ->
	"priv/logic.sh -i " ++ integer_to_list(Id).

get_timeout() ->
	1000.

sanitize_cmd(Cmd) ->
	{ok, Cmd ++ "\n"}.

%% We are recieving response lines splitted by 1000 bytes.
%% join them and return all lines
read_response(Port, RespAcc, LineAcc) ->
	receive
		{Port, {data, {eol, "EOR"}}} ->
			{ok, RespAcc};

		{Port, {data, {eol, Line}}} ->
			Response = lists:reverse([Line | LineAcc]),
			read_response(Port, [Response | RespAcc], []);

		{Port, {data, {noeol, LinePart}}} ->
			read_response(Port, RespAcc, [LinePart | LineAcc])

	after get_timeout() ->
			timeout
	end.

process_cmd(Port, Cmd) ->
	port_command(Port, Cmd),
	read_response(Port, [], []).
