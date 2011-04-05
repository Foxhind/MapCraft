-module(router).
-compile(export_all).

register_user(PieId, UserId) ->
	Pid = self(),
	ok.

lookup({'pie', PieId}) ->
	{ok, []};

lookup({'user', PieId, UserId}) ->
	{ok, []}.

send(Addr, Msg) ->
	{ok, Pids} = lookup(Addr).
