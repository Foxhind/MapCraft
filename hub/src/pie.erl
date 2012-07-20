%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.

-module(pie).
-behaviour(gen_server).

-compile(export_all).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

-include("hub.hrl").
-record(state, {id, list}).


start_link(PieId) ->
	gen_server:start_link(?MODULE, PieId, []).


%%
%% Interface
%%
subscribe(ChanId) ->
	Pie = get_pie(ChanId#hub_chan.pieid),
	gen_server:call(Pie, {set_online, ChanId, self()}),
	erlang:monitor(process, Pie).

suspend(ChanId, Pid) ->
	Pie = get_pie(ChanId#hub_chan.pieid),
	gen_server:call(Pie, {set_offline, ChanId, Pid}).

suspend(ChatId) ->
	suspend(ChatId, self()).

lookup(PieId) ->
	Pie = get_pie(PieId),
	{ok, Elems} = gen_server:call(Pie, get_all),
	Elems.

lookup(PieId, SesId) ->
	Pie = get_pie(PieId),
	{ok, Elem} = gen_server:call(Pie, {lookup, sesid, SesId}),
	Elem.

lookup(PieId, SesId, TabId) ->
	Pie = get_pie(PieId),
	ChanId = #hub_chan{pieid = PieId, sesid = SesId, tabid = TabId},
	{ok, Elem} = gen_server:call(Pie, {lookup, chanid, ChanId}),
	Elem.

get_ids() ->
	Key = {pie, '$1'},
	GProcKey = {'_', '_', Key},
	MatchHead = {GProcKey, '_', '_'},
	Ids = gproc:select(n, [{MatchHead, [], ['$1']}]),
	{ok, Ids}.


%%
%% gen_server callbacks
%%
init(PieId) ->
	register_my_pieid(PieId),
	pie_new(PieId),
	stats:incr({pie, starts}),
	process_flag(trap_exit, true),
	timer:send_interval(config:get(pie_cleanup_interval) * 1000, cleanup),
	{ok, #state{
	   id   = PieId,
	   list = new_chanlist()
	  }}.

handle_call({set_online, ChanId, Pid}, _From, #state{list = List} = State) ->
	announce_if_new(ChanId, List),
	Res = List:set_online(ChanId, Pid),
	stats:set({pie, State#state.id, channels}, List:size()),
	{reply, Res, State};

handle_call({set_offline, ChanId, Pid}, _From, #state{list = List} = State) ->
	announce_if_new(ChanId, List),
	Res = List:set_offline(chanid, ChanId, Pid),
	stats:set({pie, State#state.id, channels}, List:size()),
	{reply, Res, State};

handle_call({delete, ChanId}, _, #state{list = List} = State) ->
	Res = delete_chan_and_cleanup(List, ChanId, exit),
	stats:set({pie, State#state.id, channels}, List:size()),
	{reply, Res, State};

handle_call({lookup, Type, Id}, _From, #state{list = List} = State) ->
	Res = List:lookup(Type, Id),
	{reply, Res, State};

handle_call(get_all, _From, #state{list = List} = State) ->
	Res = List:lookup(),
	{reply, Res, State}.

handle_info(cleanup, #state{list = List, id = Id} = State) ->
	case List:size() of
		0 ->
			pie_is_empty(Id),
			{stop, normal, State};
		_N ->
			{ok, Entries} = List:lookup_expired(),
			[ delete_chan_and_cleanup(List, ChanId, timeout) || {_, ChanId, _} <- Entries ],
			stats:set({pie, State#state.id, channels}, List:size()),
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
			session_exited(ChanId, Reason);
		_ ->
			ok
	end,
	ok.

announce_if_new(ChanId, List) ->
	case List:lookup(sesid, ChanId#hub_chan.sesid) of
		{ok, []} ->
			session_joined(ChanId);
		_ ->
			ok
	end.

session_joined(ChanId) ->
	PieId = ChanId#hub_chan.pieid,
	SesId = ChanId#hub_chan.sesid,
	stats:incr({pie, PieId, joins}),
	HubReq = #hub_req{
	  pieid = PieId,
	  sesid = SesId,
	  type = async,
	  cmd = api:format_line(["session_join", PieId, SesId])
	 },
	logic:process_async(HubReq).

session_exited(ChanId, Reason) ->
	PieId = ChanId#hub_chan.pieid,
	SesId = ChanId#hub_chan.sesid,
	stats:incr({pie, PieId, exits}),
	stats:incr({pie, PieId, exits, Reason}),
	HubReq = #hub_req{
	  pieid = PieId,
	  sesid = SesId,
	  type = async,
	  cmd = api:format_line(["session_exit", PieId, SesId, Reason])
	 },
	logic:process_async(HubReq).

pie_new(PieId) ->
	HubReq = #hub_req{
	  pieid = PieId,
	  type = sync,
	  caller = self(),
	  cmd = api:format_line(["pie_create", PieId])
	 },
	{ok, "\"ok\""} = logic:process_and_wait(HubReq).

pie_is_empty(PieId) ->
	HubReq = #hub_req{
	  pieid = PieId,
	  type = async,
	  cmd = api:format_line(["pie_exit", PieId])
	 },
	logic:process_async(HubReq).

%%
%% creating and getting pie pid
%%
pieid2key(PieId) ->
	{n, l, {pie, PieId}}.

register_my_pieid(PieId) ->
	try
		true = gproc:reg(pieid2key(PieId), ignored)
	catch
		Type:What ->
			Report = [ "failed to register new pie",
					   {pieid, PieId},
					   {type, Type}, {what, What}],
			error_logger:error_report(Report),
			exit(normal)
	end.

get_pie(PieId) ->
	Key = pieid2key(PieId),
	case gproc:where(Key) of
		undefined ->
			supervisor:start_child(pie_sup, [PieId]),
			{Pid, _} = gproc:await(Key, 5000),
			Pid;
		Pid ->
			Pid
	end.

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
