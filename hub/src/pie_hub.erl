-module(pie_hub).
-behaviour(gen_server).

-compile(export_all).

start_link() ->
	gen_server:start_link({local, ?MODULE}, ?MODULE, [], []).

%%
%% Interface
%%

get_or_create(PieId) ->
	case gen_server:call(pie_hub, {lookup, PieId}) of
		notfound ->
			{ok, NewPie} = pie_sup:new_pie(PieId),
			ok = gen_server:call(pie_hub, {attach, PieId, NewPie}),
			NewPie;
		{ok, Pie} ->
			Pie
	end.

%%
%% gen_server callbacks
%%

init(PieId) ->
	{ok, idpid_list:new()}.

handle_call({lookup, Id}, _From, List) ->
	Res = idpid_list:lookup(List, {id, Id}),
	case Res of
		[] ->
			{reply, notfound, List};
		[Pid] ->
			{reply, {ok, Pid}, List}
	end;

handle_call({attach, Id, Pid}, _From, List) ->
	idpid_list:insert(List, {Id, Pid}),
	{reply, ok, List}.
