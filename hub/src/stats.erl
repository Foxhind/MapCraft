-module(stats).
-behaviour(gen_server).

-compile(export_all).

start_link() ->
	gen_server:start_link({local, ?MODULE}, ?MODULE, [], []).

%%
%% Interface
%%
incr(Key) ->
	incr(Key, 1).

incr(Key, Val) ->
	gen_server:cast(?MODULE, {update, Key, Val}).

decr(Key) ->
	decr(Key, 1).

decr(Key, Val) ->
	gen_server:cast(?MODULE, {update, Key, -Val}).

set(Key, Value) ->
	gen_server:cast(?MODULE, {set, Key, Value}).

get_next(Key) ->
	{ok, Next} = gen_server:call(?MODULE, {get_next, Key}),
	Next.

dump() ->
	{ok, Dump} = gen_server:call(?MODULE, dump),
	Dump.

pdump() ->
	Dump = lists:sort(dump()),
	[ io:format("~p ~p~n", tuple_to_list(Elem)) || Elem <- Dump ],
	ok.

%%
%% gen_server callbacks
%%
init(_Args) ->
	Tab = ets:new(stats, [set, protected]),
	{ok, Tab}.


handle_call({get_next, Key}, _From, Tab) ->
	ensure_key(Tab, Key),
	Next = ets:update_counter(Tab, Key, 1),
	{reply, {ok, Next}, Tab};

handle_call(dump, _From, Tab) ->
	Dump = ets:tab2list(Tab),
	{reply, {ok, Dump}, Tab}.


handle_cast({update, Key, Val}, Tab) ->
	ensure_key(Tab, Key),
	ets:update_counter(Tab, Key, Val),
	{noreply, Tab};

handle_cast({set, Key, Value}, Tab) ->
	ensure_key(Tab, Key),
	ets:update_element(Tab, Key, {2, Value}),
	{noreply, Tab}.

%%
%% Implementation
%%
ensure_key(Tab, Key) ->
	case ets:lookup(Tab, Key) of
		[] ->
			ets:insert(Tab, {Key, 0});
		[Obj] ->
			ok
	end.
