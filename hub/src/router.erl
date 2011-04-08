-module(router).
-compile(export_all).

-include("hub.hrl").

route_all(HubReq, Lines) ->
	Fun = fun(Line) ->
				  try ok = route_one(HubReq, api:parse_line(Line))
				  catch
					  Type:What ->
						  Report = [ "failed to route answer line",
									 {line, Line},
									 {type, Type}, {what, What},
									 {hubreq, HubReq},
									 {trace, erlang:get_stacktrace()} ],
						  error_logger:error_report(Report)
				  end
		  end,
	lists:foreach(Fun, Lines).

route_one(HubReq, ["respond", {json, Json}]) when HubReq#hub_req.type == sync ->
	error_logger:info_report([{req, HubReq}, {line, Json}]),
	HubReq#hub_req.caller ! {answer, HubReq, Json},
	ok;

route_one(H, ["respond", A]) ->
	error_logger:info_report([{req, H}, {msg, A}]),

	% response in async req is skipped
	ok;

route_one(_, ["to_session", PieId, SesId, Msg]) ->
	Line = api:format_line(event, Msg),
	{SesId, Pid, _} = pie:lookup(PieId, SesId),
	push(Pid, PieId, SesId, Line),
	ok;

route_one(_, ["to_pie", PieId, Msg]) ->
	Line = api:format_line(event, Msg),
	Elems = pie:get_all(PieId),
	[ push(Pid, PieId, SesId, Line) || {SesId, Pid, _} <- Elems ],
	ok;

route_one(_, ["to_all", Msg]) ->
	Line = api:format_line(event, Msg),
	% TODO
	ok.

push(Pid, PieId, SesId, Line) ->
	Pid ! {send, {PieId, SesId}, Line}.
