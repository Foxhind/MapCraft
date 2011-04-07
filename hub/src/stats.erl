-module(stats).
-behaviour(gen_server).

-compile(export_all).

-define(KEYS, [logic_starts]).

start_link() ->
	gen_server:start_link({local, stats}, stats, [], []).

%%
%% Interface
%%
incr(Key) ->
	{ok, Res} = gen_server:call(stats, {incr, Key}),
	Res.


%%
%% gen_server callbacks
%%
init(_Args) ->
	Tab = ets:new(stats, [set, protected]),
	[ets:insert(Tab, {Key, 0}) || Key <- ?KEYS],
	{ok, Tab}.

handle_call({incr, Key}, _From, Tab) ->
	Res = safe_increment(Tab, Key),
	{reply, Res, Tab}.

%%
%% Implementation
%%
safe_increment(Tab, Key) ->
	try
		Res = ets:update_counter(Tab, Key, 1),
		{ok, Res}
	catch
		Class:Error ->
			{fail, Class, Error}
	end.
