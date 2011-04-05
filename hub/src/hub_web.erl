%% @author Mochi Media <dev@mochimedia.com>
%% @copyright 2010 Mochi Media <dev@mochimedia.com>

%% @doc Web server for hub.

-module(hub_web).
-author("Mochi Media <dev@mochimedia.com>").

-export([start/1, stop/0, loop/2]).

%%
%% External API
%%
start(Options) ->
    {DocRoot, Options1} = get_option(docroot, Options),
    Loop = fun (Req) ->
                   ?MODULE:loop(Req, DocRoot)
           end,
    mochiweb_http:start([{name, ?MODULE}, {loop, Loop} | Options1]).

stop() ->
    mochiweb_http:stop(?MODULE).

loop(Req, DocRoot) ->
    "/" ++ Path = Req:get(path),
    try
		case Path of
			"hub/" ++ After ->
				handle_hub_req(Req:get(method), Req, After);
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

handle_hub_req('OPTIONS', Req, _Path) ->
	Req:ok({"test/plain",
			std_headers() ++
			[{"Access-Control-Allow-Methods", "POST, GET, OPTIONS"},
			 {"Access-Control-Allow-Headers", "X-Requested-With"}],
			"ok"});

handle_hub_req('POST', Req, "call/" ++ Id) ->
	Req:ok({"text/plain", std_headers(), "[\"ok\"]"});

handle_hub_req('POST', Req, "pie/" ++ Id) ->
	Req:ok({"text/plain", std_headers(), "ok"});

handle_hub_req('GET', Req, "pie/" ++ Id) ->
	wait_for_event(Req, Id).

wait_for_event(Req, Id) ->
	receive
	after 30000 ->
			Msg = io_lib:format("[\"nop\", {\"reason\": \"timeout\", \"pid\": \"~p\", \"id\": ~p}]", [self(), Id]),
			Req:ok({"text/plain", std_headers(), Msg})
	end.
	%wait_for_event(Req, Id).

%%
%% Internal API
%%
get_option(Option, Options) ->
    {proplists:get_value(Option, Options), proplists:delete(Option, Options)}.

std_headers() ->
	[{"Server", "MapCraft Hub"},
	 {"Access-Control-Allow-Origin", "http://localhost"}].

%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").


-endif.
