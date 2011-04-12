-module(chan, [ChanId, Req]).
-export([handle/1]).
-include("hub.hrl").


handle('POST') ->
	stats:incr({chan, pushes}),
	Data = binary_to_list(Req:recv_body()),
	[Type | Rest] = api:parse_line(Data),
	handle_api(Type, Rest);

handle('GET') ->
	stats:incr({chan, polls}),
	case mqueue:check_for_me(ChanId) of
		{ok, []} ->
			pie:subscribe(ChanId),
			wait_loop();
		{ok, Msgs} ->
			hub_web:ok(Req, lists:flatten(Msgs))
	end.

%%
%% API handlers
%%
handle_api(Type, [Msg]) when Type =:= "async"; Type =:= "sync" ->
	HubReq = format_hub_req(Type, Msg),
	push(HubReq).


%%
%% Pushing
%%
push(HubReq = #hub_req{type = async}) ->
	stats:incr({chan, pushes, async}),
	ok = logic:process(HubReq),
	hub_web:ok(Req, "ok");

push(HubReq = #hub_req{type = sync}) ->
	stats:incr({chan, pushes, sync}),
	ok = logic:process(HubReq),
	wait_for_answer(HubReq, 0).

wait_for_answer(HubReq, Ref) ->
	receive
		{answer, HubReq, Data} ->
			hub_web:ok(Req, Data);

		{follow_me, Pid} ->
			catch erlang:demonitor(Ref),
			NewRef = erlang:monitor(process, Pid),
			wait_for_answer(HubReq, NewRef);

		{'DOWN', MonRef, process, From, Reason} ->
			Report = ["sync request failed",
					  {hubreq, HubReq},
					  {following, From},
					  {reason, Reason},
					  {req, Req}],
			error_logger:error_report(Report),
			hub_web:fail(Req)
	end.

format_hub_req(Type, Msg) ->
	PieId = ChanId#hub_chan.pieid,
	SesId = ChanId#hub_chan.sesid,
	#hub_req{
			  pieid = PieId,
			  sesid = SesId,
			  type = list_to_atom(Type),
			  caller = self(),
			  cmd = api:format_line(["from", Type, PieId, SesId, Msg])
			}.


%%
%% Polling
%%
wait_loop() ->
	receive
		{send, Router, ChanId, Line} ->
			router:got_it(Router),
			{ok, Events} = accomulate_events(Router, [Line]),
			hub_web:ok(Req, lists:flatten(Events));
		{send, AnotherRouter, _, _} ->
			router:not_me(AnotherRouter),
			wait_loop()
	after config:get(poll_timeout) * 1000 ->
			hub_web:ok(Req, <<"event!json:[\"nop\", {\"reason\": \"poll timeout\"}]\n">>)
	end.

accomulate_events(Router, Acc) ->
	erlang:monitor(process, Router),
	accomulate_events0(Router, Acc).

accomulate_events0(Router, Acc) ->
	receive
		{send, Router, ChanId, Line} ->
			router:got_it(Router),
			accomulate_events0(Router, [Line | Acc]);

		{send, AnotherRouter, ChanId, _} ->
			router:defer(AnotherRouter),
			accomulate_events0(Router, Acc);

		{send, AnotherRouter, _, _} ->
			router:not_me(AnotherRouter),
			accomulate_events0(Router, Acc);

		{'DOWN', _, process, Router, _} ->
			{ok, lists:reverse(Acc)};

		Any ->
			error_logger:error_report(["Unknown message in event accomulation",
									   {msg, Any}])
	after 1000 ->
			{error, timeout}
	end.
