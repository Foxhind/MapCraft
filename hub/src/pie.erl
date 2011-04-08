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

lookup(PieId, SesId) ->
	Pie = pie_hub:get_or_create(PieId),
	{ok, Elem} = gen_server:call(Pie, {lookup, SesId}),
	Elem.

get_all(PieId) ->
	Pie = pie_hub:get_or_create(PieId),
	{ok, Elems} = gen_server:call(Pie, get_all),
	Elems.


%%
%% gen_server callbacks
%%
init(PieId) ->
	{ok, #state{
	   id   = PieId,
	   list = idpid_list:new()
	  }}.

handle_call({subscribe, SesId}, {Pid, _}, State) ->
	link(Pid),
	idpid_list:insert(State#state.list, {SesId, Pid, active}),
	{reply, ok, State};

handle_call(logout, {Pid, _}, State) ->
	idpid_list:delete(State#state.list, {pid, Pid}),
	{reply, ok, State};

handle_call({lookup, SesId}, _From, State) ->
	[Elem] = idpid_list:lookup(State#state.list, {id, SesId}),
	{reply, {ok, Elem}, State};

handle_call(get_all, _From, State) ->
	%TODO
	{reply, {ok, []}, State}.

handle_info(Info, State) ->
	io:write("Pie got ~p~n", [Info]),
	{noreply, State}.
