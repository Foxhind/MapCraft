-module(pie).
-behaviour(gen_server).

-compile(export_all).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

-include("hub.hrl").
-record(state, {id, list}).


start_link(PieId) ->
	gen_server:start_link(?MODULE, [PieId], []).


%%
%% Interface
%%
subscribe(ChanId) ->
	Pie = pie_hub:get_or_create(ChanId#hub_chan.pieid),
	gen_server:call(Pie, {set_online, ChanId, self()}).

suspend(ChanId, Pid) ->
	Pie = pie_hub:get_or_create(ChanId#hub_chan.pieid),
	gen_server:call(Pie, {set_offline, ChanId, Pid}).

lookup(PieId) ->
	Pie = pie_hub:get_or_create(PieId),
	{ok, Elems} = gen_server:call(Pie, get_all),
	Elems.

lookup(PieId, SesId) ->
	Pie = pie_hub:get_or_create(PieId),
	{ok, Elem} = gen_server:call(Pie, {lookup, SesId}),
	Elem.


%%
%% gen_server callbacks
%%
init(PieId) ->
	{ok, #state{
	   id   = PieId,
	   list = new_chanlist()
	  }}.

handle_call({set_online, ChanId, Pid}, _From, #state{list = List} = State) ->
	Res = List:set_online(ChanId, Pid),
	{reply, Res, State};

handle_call({set_offline, ChanId, Pid}, _From, #state{list = List} = State) ->
	Res = List:set_offline(chanid, ChanId, Pid),
	{reply, Res, State};

handle_call({delete, ChanId}, _, #state{list = List} = State) ->
	Res = List:delete(chanid, ChanId),
	{reply, Res, State};

handle_call({lookup, SesId}, _From, #state{list = List} = State) ->
	Res =  format_lookup(List:lookup(sesid, SesId)),
	{reply, Res, State};

handle_call(get_all, _From, #state{list = List} = State) ->
	Res =  format_lookup(List:lookup()),
	{reply, Res, State}.


handle_info(_Msg, State) ->
	{noreply, State}.

handle_cast(_Msg, State) ->
	{noreply, State}.

code_change(_, State, _) ->
	{ok, State}.

terminate(_Reason, _State) ->
	ok.

%%
%% Helpers
%%
new_chanlist() ->
	%% TODO: somehow this need to be moved into chanlist.erl
	chanlist:new(
	  ets:new(bychan, [set, protected]),
	  ets:new(byses,  [bag, protected, {keypos, 2}])
	 ).

format_lookup({ok, Entries}) ->
	List = [{Pid, ChanId, State} || {ChanId, _, Pid, State} <- Entries],
	{ok, List};

format_lookup(Any) ->
	Any.


%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").

gen_chan(A, B, C) ->
	{ #hub_chan{pieid = A, sesid = B, tabid = C}, make_ref() }.

tmp_list() ->
	List = new_chanlist(),
	{Chan11, Pid11} = gen_chan(1,1,1),
	{Chan12, Pid12} = gen_chan(1,1,2),
	{Chan2,  Pid2}  = gen_chan(1,2,1),
	ok = List:set_online(Chan11, Pid11),
	ok = List:set_online(Chan12, Pid12),
	ok = List:set_online(Chan2,  Pid2),
	{List, {Chan11, Pid11}, {Chan12, Pid12}, {Chan2, Pid2}}.

test_chan(List, ChanId, Pid, State) ->
	{ok, [{ChanId, _, Pid, State}]} = List:lookup(chanid, ChanId).

chanlist_lookup_test() ->
	{List, {Chan11, Pid11}, {Chan12, Pid12}, _} = tmp_list(),
	{ok,
	 [{Pid11, Chan11, online}]
	} = format_lookup(List:lookup(chanid, Chan11)),
	{ok,
	 [{Pid11, Chan11, online},
	  {Pid12, Chan12, online}]
	} = format_lookup(List:lookup(sesid, 1)).

chanlist_offline_test() ->
	{List, {Chan11, Pid11}, {Chan12, Pid12}, {Chan2, Pid2}} = tmp_list(),
	%% just set 12 to offline
	ok = List:set_offline(chanid, Chan12, Pid12),
	%% twice
	ok = List:set_offline(chanid, Chan12, Pid12),
	%% setting to offline with another pid should return new one
	{new_pid, Pid2} = List:set_offline(chanid, Chan2, make_ref()),
	%% and test
	test_chan(List, Chan11, Pid11, online),
	test_chan(List, Chan12, Pid12, offline),
	test_chan(List, Chan2,  Pid2,  online).

chanlist_online_test() ->
	{List, {Chan11, Pid11}, {Chan12, Pid12}, _} = tmp_list(),
	NewPid = make_ref(),
	ok = List:set_offline(chanid, Chan12, Pid12),
	ok = List:set_online(Chan12, NewPid),
	%% test
	test_chan(List, Chan11, Pid11, online),
	test_chan(List, Chan12, NewPid, online).


-endif.
