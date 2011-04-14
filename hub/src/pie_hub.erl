-module(pie_hub).
-behaviour(gen_server).

-compile(export_all).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

start_link() ->
	gen_server:start_link({local, ?MODULE}, ?MODULE, [], []).

%%
%% Interface
%%

get_or_create(PieId) ->
	case gen_server:call(pie_hub, {lookup, PieId}) of
		notfound ->
			{ok, NewPie} = pie_sup:new_pie(PieId),
			NewPie;
		{ok, Pie} ->
			Pie
	end.

attach_me(PieId) ->
	ok = gen_server:call(?MODULE, {attach, PieId, self()}).


%%
%% gen_server callbacks
%%

init(_Args) ->
	process_flag(trap_exit, true),
	{ok, idpid_list:new()}.

handle_call({lookup, Id}, _From, List) ->
	Res = idpid_list:lookup(List, {id, Id}),
	case Res of
		[] ->
			{reply, notfound, List};
		[{Id, Pid}] ->
			{reply, {ok, Pid}, List}
	end;

handle_call({attach, Id, Pid}, _From, List) ->
	link(Pid),
	idpid_list:insert(List, {Id, Pid}),
	{reply, ok, List}.

handle_info({'EXIT', Pid, _Reason}, List) ->
	idpid_list:delete(List, {pid, Pid}),
	{noreply, List}.

handle_cast(_Msg, State) ->
	{noreply, State}.

code_change(_, State, _) ->
	{ok, State}.

terminate(_Reason, _State) ->
	ok.
