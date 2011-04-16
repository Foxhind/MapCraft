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
	gen_server:call(Pie, {set_online, ChanId, self()}),
	erlang:monitor(process, Pie).

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
	pie_hub:attach_me(PieId),
	process_flag(trap_exit, true),
	timer:send_interval(config:get(pie_cleanup_interval) * 1000, cleanup),
	{ok, #state{
	   id   = PieId,
	   list = new_chanlist()
	  }}.

handle_call({set_online, ChanId, Pid}, _From, #state{list = List} = State) ->
	%% check if. this is a new user
	case List:lookup(sesid, ChanId#hub_chan.sesid) of
		{ok, []} ->
			user_joined(ChanId);
		_ ->
			ok
	end,
	%% register
	Res = List:set_online(ChanId, Pid),
	{reply, Res, State};

handle_call({set_offline, ChanId, Pid}, _From, #state{list = List} = State) ->
	Res = List:set_offline(chanid, ChanId, Pid),
	{reply, Res, State};

handle_call({delete, ChanId}, _, #state{list = List} = State) ->
	Res = delete_chan_and_cleanup(List, ChanId, exit),
	{reply, Res, State};

handle_call({lookup, SesId}, _From, #state{list = List} = State) ->
	Res = List:lookup(sesid, SesId),
	{reply, Res, State};

handle_call(get_all, _From, #state{list = List} = State) ->
	Res = List:lookup(),
	{reply, Res, State}.

handle_info(cleanup, #state{list = List, id = Id} = State) ->
	{ok, Entries} = List:lookup_expired(),
	[ delete_chan_and_cleanup(List, ChanId, timeout) || {_, ChanId, _} <- Entries ],
	case List:size() of
		0 ->
			pie_is_empty(Id),
			{stop, normal, State};
		_N ->
			{noreply, State}
	end;

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

delete_chan_and_cleanup(List, ChanId, Reason) ->
	ok = List:delete(chanid, ChanId),
	mqueue:check_for_me(ChanId),
	% check is there another
	% channel for this SesId?
	case List:lookup(sesid, ChanId#hub_chan.sesid) of
		{ok, []} ->
			user_exited(ChanId, Reason);
		_ ->
			ok
	end,
	ok.

user_joined(ChanId) ->
	PieId = ChanId#hub_chan.pieid,
	SesId = ChanId#hub_chan.sesid,
	HubReq = #hub_req{
	  pieid = PieId,
	  sesid = SesId,
	  type = async,
	  cmd = api:format_line(["session_join", PieId, SesId])
	 },
	logic:process_async(HubReq).

user_exited(ChanId, Reason) ->
	PieId = ChanId#hub_chan.pieid,
	SesId = ChanId#hub_chan.sesid,
	HubReq = #hub_req{
	  pieid = PieId,
	  sesid = SesId,
	  type = async,
	  cmd = api:format_line(["session_exit", PieId, SesId, Reason])
	 },
	logic:process_async(HubReq).

pie_is_empty(PieId) ->
	HubReq = #hub_req{
	  pieid = PieId,
	  type = async,
	  cmd = api:format_line(["pie_exit", PieId])
	 },
	logic:process_async(HubReq).


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
	[{ChanId, _, Pid, State, _}] = List:lookup_raw(chanid, ChanId).

chanlist_lookup_test() ->
	{List, {Chan11, Pid11}, {Chan12, Pid12}, _} = tmp_list(),
	{ok,
	 [{Pid11, Chan11, online}]
	} = List:lookup(chanid, Chan11),
	{ok,
	 [{Pid11, Chan11, online},
	  {Pid12, Chan12, online}]
	} = List:lookup(sesid, 1).

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
