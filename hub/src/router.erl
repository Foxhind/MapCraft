-module(router).
-compile(export_all).

-include("hub.hrl").

%%
%% Interface
%%
spawn_new(HubReq, Lines) ->
	spawn(?MODULE, route_all, [HubReq, Lines]).

got_it(Pid) ->
	Pid ! {self(), ok}.

not_me(Pid) ->
	Pid !  {self(), not_me}.

defer(Pid) ->
	Pid !  {self(), defer}.

%%
%% Routing
%%
route_all(HubReq, Lines) ->
	stats:incr({router, spawns}),
	hub_web:follow_me(HubReq),
	[ route_one_safely(HubReq, Line) || Line <- Lines ].

route_one_safely(HubReq, Line) ->
	try
		[Cmd | Rest] =  api:parse_line(Line),
		ok = route_one(Cmd, HubReq, Rest)
	catch
		Type:What ->
			stats:incr({router, lost}),
			Report = [ "failed to route answer line",
					   {line, Line},
					   {type, Type}, {what, What},
					   {hubreq, HubReq},
					   {trace, erlang:get_stacktrace()} ],
			error_logger:error_report(Report)
	end.

route_one("respond", HubReq, [{json, Json}]) ->
	sync = HubReq#hub_req.type,
	stats:incr({router, to, caller}),
	HubReq#hub_req.caller ! {answer, HubReq, Json},
	ok;

route_one("to_sender", HubReq, [Msg]) ->
	stats:incr({router, to, sender}),
	PieId = HubReq#hub_req.pieid,
	SesId = HubReq#hub_req.sesid,
	TabId = HubReq#hub_req.tabid,
	Dests = pie:lookup(PieId, SesId, TabId),
	push_event_to_chans(Dests, Msg),
	ok;

route_one("to_session", _, [PieId, SesId, Msg]) ->
	stats:incr({router, to, session}),
	Dests = pie:lookup(PieId, SesId),
	push_event_to_chans(Dests, Msg),
	ok;

route_one("to_pie", _, [PieId, Msg]) ->
	stats:incr({router, to, pie}),
	Dests = pie:lookup(PieId),
	push_event_to_chans(Dests, Msg),
	ok;

route_one("stat", _, Args) ->
	stats:external(Args),
	ok.

%route_one("to_all", _, [_Msg]) ->
%   TODO
%	ok.

%%
%% Pushing event to channels
%%
push_event_to_chans([_|_] = Dests, Msg) ->
	Cmd = api:format_line([event, Msg]),
	[ push(Dest, Cmd) || Dest <- Dests ].

push({_, ChanId, offline}, Cmd) ->
	ok = mqueue:store(ChanId, Cmd);

push({Pid, ChanId, online}, Cmd) ->
	Pid ! {send, self(), ChanId, Cmd},
	receive
		{Pid, ok} ->
			stats:incr({router, to, channel}),
			ok;
		{Pid, _Ans} ->
			ok = mqueue:store(ChanId, Cmd)
	after 50 ->
			case pie:suspend(ChanId, Pid) of
				ok ->
					ok = mqueue:store(ChanId, Cmd);
				{new_pid, NewPid} ->
					push({NewPid, ChanId, online}, Cmd)
			end
	end.
