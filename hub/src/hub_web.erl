%% @author Mochi Media <dev@mochimedia.com>
%% @copyright 2010 Mochi Media <dev@mochimedia.com>

%% @doc Web server for hub.

-module(hub_web).
-author("Mochi Media <dev@mochimedia.com>").

-export([start/1, stop/0, loop/1]).
-export([ok/2]).

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
	Method = Req:get(method),
	Hub_prefix = config:get(hub_prefix),
	try
		case {Method, string:tokens(Path, "/")} of

			%% CORS support
			{'OPTIONS', _} ->
				Req:ok({_Content = "test/plain",
						_Headers = std_headers() ++
						[{"Access-Control-Allow-Methods", "POST, GET, OPTIONS"},
						 {"Access-Control-Allow-Headers", "X-Requested-With"}],
						"ok"});

			%% /hub/* -> our hub requests
			{Method, [ Hub_prefix | Rest ]}->
				handle_hub_req(Method, Req, Rest);

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

%% Method on 'hub/pie/PieId/SesId/TabId -- subscribe/publish in channels
handle_hub_req(Method, Req, ["pie", PieId, SesId, TabId]) ->
	ChanId = #hub_chan{ pieid = PieId,
						sesid = SesId,
						tabid = TabId },
	Chan = chan:new(ChanId, Req),
	Chan:handle(Method).


%%
%% Helpers
%%
ok(Req, Msg) ->
	Req:ok({_Content = "test/plain",
			_Headers = std_headers(),
			Msg}).

std_headers() ->
	[{"Server", "MapCraft Hub (Mochiweb)"},
	 {"Access-Control-Allow-Origin", config:get(origin)}].

%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").

-endif.
