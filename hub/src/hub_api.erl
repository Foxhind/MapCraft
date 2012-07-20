%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.

-module(hub_api).

-export([handle/3]).
-compiler(export_all).
-include("hub.hrl").


handle('GET', Req, ["stats"]) ->
	hub_web:ok(Req, stats:fdump());

handle(Method, Req, ["session", SesId, Action]) ->
	session_action(Method, Req, SesId, Action).

session_action('POST', Req, SesId, Action) ->
	{ok, Ids} = pie:get_ids(),
	[ send_ses_action(PieId, SesId, Action) || PieId <- Ids ],
	hub_web:ok(Req, "ok").

send_ses_action(PieId, SesId, Action) ->
	case pie:lookup(PieId, SesId) of
		[] ->
			ok;
		_ ->
			HubReq = #hub_req{
			  pieid = PieId,
			  sesid = SesId,
			  type = async,
			  cmd = api:format_line(["session_action", PieId, SesId, Action])
			 },
			ok = logic:process(HubReq)
	end.
