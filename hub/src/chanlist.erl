-module(chanlist, [ByChan, BySes]).
-compile(export_all).

-include("hub.hrl").

set_online(ChanId, Pid) ->
	ok = delete(chanid, ChanId),
	insert(ChanId, Pid, online).

set_offline(chanid, ChanId, Pid) ->
	case lookup(chanid, ChanId) of
		{ok, [Entry]} ->
			set_offline(entry, Entry, Pid);
		{ok, []} ->
			ok
	end;

set_offline(entry, Entry, Pid) ->
	{ChanId, _, CurPid, CurState} = Entry,
	case {CurPid, CurState} of
		{_, offline} ->
			ok;
		{Pid, online} ->
			ok = delete(chanid, ChanId),
			insert(ChanId, Pid, offline);
		{CurPid, online} ->
			{new_pid, CurPid}
	end.


lookup(sesid, SesId) ->
	{ok, ets:lookup(BySes, SesId)};

lookup(chanid, ChanId) ->
	{ok, ets:lookup(ByChan, ChanId)}.

lookup() ->
    {ok, ets:tab2list(ByChan)}.

insert(ChanId, Pid, State) ->
	Entry = {ChanId, ChanId#hub_chan.sesid, Pid, State},
	ets:insert(ByChan, Entry),
	ets:insert(BySes, Entry),
	ok.

delete(Type, Id) ->
	{ok, Entries} = lookup(Type, Id),
	lists:foreach( fun(Entry) ->
						   ets:delete_object(ByChan, Entry),
						   ets:delete_object(BySes, Entry)
				   end, Entries ),
	ok.
