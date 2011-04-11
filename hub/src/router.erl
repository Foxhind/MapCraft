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
	[ route_one_safely(HubReq, Line) || Line <- Lines ].

route_one_safely(HubReq, Line) ->
	try
		[Cmd | Rest] =  api:parse_line(Line),
		ok = route_one(Cmd, HubReq, Rest)
	catch
		Type:What ->
			Report = [ "failed to route answer line",
					   {line, Line},
					   {type, Type}, {what, What},
					   {hubreq, HubReq},
					   {trace, erlang:get_stacktrace()} ],
			error_logger:error_report(Report)
	end.

route_one("respond", HubReq = #hub_req{type = sync}, [{json, Json}]) ->
	HubReq#hub_req.caller ! {answer, HubReq, Json},
	ok;

route_one("respond", #hub_req{type = async}, _) ->
	% response in async req is skipped
	ok;

route_one("to_session", _, [PieId, SesId, Msg]) ->
	Dests = pie:lookup(PieId, SesId),
	push_event_to_chans(Dests, Msg),
	ok;

route_one("to_pie", _, [PieId, Msg]) ->
	Dests = pie:lookup(PieId),
	push_event_to_chans(Dests, Msg),
	ok.

%route_one("to_all", _, [_Msg]) ->
%   TODO
%	ok.

%%
%% Pushing event to channels
%%
push_event_to_chans(Dests, Msg) ->
	Cmd = api:format_line([event, Msg]),
	[ push(Dest, Cmd) || Dest <- Dests ].

push({_, ChanId, offline}, Cmd) ->
	ok = mqueue:store(ChanId, Cmd);

push({Pid, ChanId, online}, Cmd) ->
	Pid ! {send, self(), ChanId, Cmd},
	receive
		{Pid, ok} ->
			ok;
		{Pid, Ans} ->
			ok = mqueue:store(ChanId, Cmd)
	after 50 ->
			case pie:suspend(ChanId, Pid) of
				ok ->
					ok = mqueue:store(ChanId, Cmd);
				{new_pid, NewPid} ->
					push({NewPid, ChanId, online}, Cmd)
			end
	end.
