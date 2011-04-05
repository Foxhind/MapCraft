-module(pie).
-behaviour(gen_server).

-compile(export_all).

-record(state, {id, list}).

start_link(PieId) ->
	gen_server:start_link(?MODULE, [PieId], []).

%%
%% Interface
%%

login(Pie, SessionId) ->
	ok = gen_server:call(Pie, {login, SessionId}).

logout(Pie, _SessionId) ->
	ok = gen_server:call(Pie, logout).

lookup(Pie, SessionId) ->
	{ok, Pid} = gen_server:call(Pie, {lookup, SessionId}),
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

handle_call({login, Id}, From, State) ->
	idpid_list:insert(State#state.list, {Id, From}),
	{reply, ok, State};

handle_call(logout, From, State) ->
	idpid_list:delete(State#state.list, {pid, From}),
	{reply, ok, State};

handle_call({lookup, Id}, _From, State) ->
	[Pid] = idpid_list:lookup(State#state.list, {id, Id}),
	{reply, {ok, Pid}, State}.
