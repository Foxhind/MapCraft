-module(idpid_list).
-compile(export_all).

-record(idpid, {byid, bypid}).

new() ->
	ById =  ets:new(list, [set, private]),
	ByPid = ets:new(list, [set, private, {keypos, 2}]),
 	#idpid{byid = ById, bypid = ByPid}.

lookup(List, {id, Id}) ->
	ets:lookup(List#idpid.byid, Id);
lookup(List, {pid, Pid}) ->
	ets:lookup(List#idpid.bypid, Pid).

insert(List, Elem) ->
	[ets:insert(T, Elem) || T <- [List#idpid.byid, List#idpid.bypid]],
	true.

extract_keys(Elem) ->
	{element(1, Elem), element(2, Elem)}.

delete(List, {id, _Id}=Key) ->
	Elems = lookup(List, Key),
	[delete(List, E) || E <- Elems];

delete(List, {pid, _Pid}=Key) ->
	Elems = lookup(List, Key),
	[delete(List, E) || E <- Elems];

delete(List, Elem) ->
	{Id, Pid} = extract_keys(Elem),
	ets:delete(List#idpid.byid, Id),
	ets:delete(List#idpid.bypid, Pid).


%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").

tmp_list() ->
	List = new(),
	insert(List, {1,100, temp}),
	insert(List, {2,200}),
	insert(List, {3,300}),
	List.

extract_keys_test() ->
	Elem = {1,2,3,4,5},
	{1,2} = extract_keys(Elem).

lookup_test() ->
	List = tmp_list(),
	[{2,200}] = lookup(List, {id, 2}),
	[{3,300}] = lookup(List, {pid,300}).

delete_by_id_test() ->
	List = tmp_list(),
	delete(List, {id, 2}),
	[] = lookup(List, {id, 2}).

delete_by_pid_test() ->
	List = tmp_list(),
	delete(List, {pid, 200}),
	[] = lookup(List, {id, 2}).

delete_by_elem_test() ->
	List = tmp_list(),
	delete(List, {2, 200}),
	[] = lookup(List, {id, 2}).


-endif.
