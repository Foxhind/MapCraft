-module(pie).
-behaviour(gen_server).

-compile(export_all).

-record(state, {id, list}).
-include("hub.hrl").

start_link(PieId) ->
	gen_server:start_link(?MODULE, [PieId], []).

%%
%% Interface
%%
subscribe(ChanId) ->
	Pie = pie_hub:get_or_create(ChanId#hub_chan.pieid),
	ok = gen_server:call(Pie, {subscribe, ChanId#hub_chan.sesid}).

lookup(Pie, SesId) ->
	{ok, Pid, _} = gen_server:call(Pie, {lookup, SesId}),
	Pid.

all(Pie) ->
	{ok, Pids} = gen_server:call(Pie, get_all).


%%
%% gen_server callbacks
%%

init(PieId) ->
	{ok, #state{
	   id   = PieId,
	   list = idpid_list:new()
	  }}.

handle_call({subscribe, SesId}, From, State) ->
	{Pid, _} = From,
	link(Pid),
	idpid_list:insert(State#state.list, {SesId, Pid, active}),
	{reply, ok, State};

handle_call(logout, From, State) ->
	{Pid, _} = From,
	idpid_list:delete(State#state.list, {pid, Pid}),
	{reply, ok, State};

handle_call({lookup, SesId}, _From, State) ->
	[{SesId, Pid, State}] = idpid_list:lookup(State#state.list, {id, SesId}),
	{reply, {ok, Pid, State}, State}.

handle_info(Info, State) ->
	io:write("Pie got ~p~n", [Info]),
	{noreply, State}.
