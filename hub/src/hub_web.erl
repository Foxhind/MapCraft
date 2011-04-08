%% @author Mochi Media <dev@mochimedia.com>
%% @copyright 2010 Mochi Media <dev@mochimedia.com>

%% @doc Web server for hub.

-module(hub_web).
-author("Mochi Media <dev@mochimedia.com>").

-export([start/1, stop/0, loop/1]).

-include("hub.hrl").
-define(LOOP, {?MODULE, loop}).
%%
%% External API
%%
start(Options) ->
    mochiweb_http:start([{name, ?MODULE}, {loop, ?LOOP} | Options]).

stop() ->
    mochiweb_http:stop(?MODULE).

loop(Req) ->
    Path = Req:get(path),
    try
		case string:tokens(Path, "/") of
			["hub" | Rest ] ->
				handle_hub_req(Req:get(method), Req, Rest);
			 _ ->
				Req:not_found()
		end
    catch
        Type:What ->
            Report = ["web request failed",
                      {path, Path},
                      {type, Type}, {what, What},
                      {trace, erlang:get_stacktrace()}],
            error_logger:error_report(Report),
            %% NOTE: mustache templates need \ because they are not awesome.
            Req:respond({500, [{"Content-Type", "text/plain"}],
                         "request failed, sorry\n"})
    end.

%%
%% Internal API - handling requests to hub/
%%

%% OPTIONS is used for CORS
handle_hub_req('OPTIONS', Req, _) ->
	Req:ok({_Content = "test/plain",
			_Headers = std_headers() ++
			[{"Access-Control-Allow-Methods", "POST, GET, OPTIONS"},
			 {"Access-Control-Allow-Headers", "X-Requested-With"}],
			"ok"});

%% POST - is a sync/async pushes from client to logic
handle_hub_req('POST', Req, ["pie", PieId, SesId]) ->
	Data = binary_to_list(Req:recv_body()),
	Parts = api:parse_line(Data),
	Res = process_req(PieId, SesId, Parts),
	ok(Req, Res);

%% GET - is subscribtion and waiting for events
handle_hub_req('GET', Req, ["pie", PieId, SesId]) ->
	Chan = #hub_chan{ pieid = PieId,
					  sesid = SesId },
	pie:subscribe(Chan),
	wait_for_event(Req, Chan).


process_req(PieId, SesId, ["async", Msg]) ->
	HubReq = format_hub_req(async, PieId, SesId, Msg),
	ok = logic:process(HubReq),
	"ok";

process_req(PieId, SesId, ["sync", Msg]) ->
	HubReq = format_hub_req(sync, PieId, SesId, Msg),
	ok = logic:process(HubReq),
	receive
		{answer, HubReq, Data} ->
			Data
	end.


wait_for_event(Req, Chan) ->
	receive
		{send, From, Chan, Line} ->
			From ! {self(), ok},
			{ok, Events} = accomulate_events(From, Chan, [Line]),
			ok(Req, lists:flatten(Events));
		{send, From, _, Line} ->
			From ! {self(), not_me},
			wait_for_event(Req, Chan)
	after 30000 ->
			Msg = io_lib:format("event!json:[\"nop\", {\"reason\": \"timeout\", \"pid\": \"~w\", \"id\": [~p, ~p]}]~n",
								[self(), Chan#hub_chan.pieid,  Chan#hub_chan.sesid]),
			ok(Req, Msg)
	end.

accomulate_events(From, Chan, Acc) ->
	erlang:monitor(process, From),
	accomulate_events0(From, Chan, Acc).

accomulate_events0(From, Chan, Acc) ->
	receive
		{send, From, Chan, Line} ->
			From ! {self(), ok},
			accomulate_events0(From, Chan, [Line | Acc]);
		{send, _, Chan, _} ->
			From ! {self(), defer},
			accomulate_events0(From, Chan, Acc);
		{send, _, _, _} ->
			From ! {self(), not_me},
			accomulate_events0(From, Chan, Acc);
		{'DOWN', _, process, From, _} ->
			{ok, lists:reverse(Acc)};
		Any ->
			error_logger:error_report(["Unknown message in event accomulation",
									   {msg, Any}])
	after 1000 ->
			{error, timeout}
	end.


%%
%% Helpers
%%
get_option(Option, Options) ->
    {proplists:get_value(Option, Options), proplists:delete(Option, Options)}.

std_headers() ->
	[{"Server", "MapCraft Hub (Mochiweb)"},
	 {"Access-Control-Allow-Origin", "http://localhost"}].

ok(Req, Msg) ->
	Req:ok({_Content = "test/plain",
			_Headers = std_headers(),
			Msg}).

format_hub_req(Type, PieId, SesId, Msg) ->
	HubReq = #hub_req{
	  pieid = PieId,
	  sesid = SesId,
	  type = Type,
	  caller = self(),
	  cmd = api:format_line(["from", atom_to_list(Type), PieId, SesId, Msg]) ++ "\n"
	 }.


%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").

-endif.
