-module(router).
-compile(export_all).

-include("hub.hrl").

spawn_new(HubReq, Lines) ->
	spawn(?MODULE, route_all, [HubReq, Lines]).

route_all(HubReq, Lines) ->
	[ route_one_safely(HubReq, Line) || Line <- Lines ].



route_one_safely(HubReq, Line) ->
	try ok = route_one(HubReq, api:parse_line(Line))
	catch
		Type:What ->
			Report = [ "failed to route answer line",
					   {line, Line},
					   {type, Type}, {what, What},
					   {hubreq, HubReq},
					   {trace, erlang:get_stacktrace()} ],
			error_logger:error_report(Report)
	end.

route_one(HubReq, ["respond", {json, Json}]) when HubReq#hub_req.type == sync ->
	HubReq#hub_req.caller ! {answer, HubReq, Json},
	ok;

route_one(H, ["respond", A]) ->
	% response in async req is skipped
	ok;

route_one(_, ["to_session", PieId, SesId, Msg]) ->
	Line = api:format_line([event, Msg]),
	{SesId, Pid, _} = pie:lookup(PieId, SesId),
	push(Pid, PieId, SesId, Line),
	ok;

route_one(_, ["to_pie", PieId, Msg]) ->
	Line = api:format_line([event, Msg]),
	Elems = pie:get_all(PieId),
	[ push(Pid, PieId, SesId, Line) || {SesId, Pid, _} <- Elems ],
	ok;

route_one(_, ["to_all", Msg]) ->
	Line = api:format_line([event, Msg]),
	% TODO
	ok.

push(Pid, PieId, SesId, Line) ->
	Chan = #hub_chan{pieid = PieId, sesid = SesId},
	Pid ! {send, self(), Chan, Line ++ "\n"},
	receive
		{Pid, ok} ->
			ok;
		{Pid, Ans} ->
			error_logger:error_report([ "LOST EVENT! It should be deferred",
										{chan, Chan}, {line, Line},
										{answer, Ans} ])
	after 50 ->
			error_logger:error_report([ "LOST EVENT! Answer timeout, should be deferred",
										{chan, Chan}, {line, Line} ])

	end.
