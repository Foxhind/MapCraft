-module(chanlist, [ByChan, BySes]).
-compile(export_all).

-include("hub.hrl").

%%
%% online/offline
%%
set_online(ChanId, Pid) ->
	ok = delete(chanid, ChanId),
	insert(ChanId, Pid, online).

set_offline(chanid, ChanId, Pid) ->
	case lookup_raw(chanid, ChanId) of
		[Entry] ->
			set_offline(entry, Entry, Pid);
		[] ->
			insert(ChanId, Pid, offline)
	end;

set_offline(entry, Entry, Pid) ->
	{ChanId, _, CurPid, CurState, _} = Entry,
	case {CurPid, CurState} of
		{_, offline} ->
			ok;
		{Pid, online} ->
			ok = delete(chanid, ChanId),
			insert(ChanId, Pid, offline);
		{CurPid, online} ->
			{new_pid, CurPid}
	end.

%%
%% Lookup and other info
%%
lookup(Type, Id) ->
	{ok, fmt_lookup(lookup_raw(Type, Id))}.

lookup() ->
	{ok, fmt_lookup(lookup_raw())}.

lookup_raw(sesid, SesId) ->
	ets:lookup(BySes, SesId);

lookup_raw(chanid, ChanId) ->
	ets:lookup(ByChan, ChanId).

lookup_raw() ->
    ets:tab2list(ByChan).

lookup_expired() ->
	{ok, fmt_lookup(lookup_expired_raw())}.

lookup_expired_raw() ->
	Now = now_secs(),
	OfflineExpire = Now - config:get(chan_expire),
	OnlineExpire = OfflineExpire - config:get(poll_timeout),
	ets:foldl(fun({_, _, _, State, Mtime} = X, Acc) ->
					  case State of
						  online when Mtime < OnlineExpire ->
							  [ X | Acc];
						  offline when Mtime < OfflineExpire ->
							  [ X | Acc];
						  _ ->
							  Acc
					  end
			  end, [], ByChan).

size() ->
	Info = ets:info(ByChan),
	proplists:get_value(size, Info).

%%
%% List modifications
%%
insert(ChanId, Pid, State) ->
	insert(ChanId, Pid, State, now_secs()).

insert(ChanId, Pid, State, Mtime) ->
	Entry = {ChanId, ChanId#hub_chan.sesid, Pid, State, Mtime},
	ets:insert(ByChan, Entry),
	ets:insert(BySes, Entry),
	ok.

delete(Type, Id) ->
	Entries = lookup_raw(Type, Id),
	lists:foreach( fun(Entry) ->
						   ets:delete_object(ByChan, Entry),
						   ets:delete_object(BySes, Entry)
				   end, Entries ),
	ok.

%%
%% Helpers
%%0
fmt_lookup(Entries) ->
	[{Pid, ChanId, State} || {ChanId, _, Pid, State, _} <- Entries].

now_secs() ->
	calendar:datetime_to_gregorian_seconds(calendar:universal_time()).
