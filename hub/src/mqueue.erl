%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.

-module(mqueue).
-behaviour(gen_server).

-compile(export_all).
-export([init/1, handle_call/3, handle_cast/2, handle_info/2, code_change/3, terminate/2]).

start_link() ->
	gen_server:start_link({local, ?MODULE}, ?MODULE, [], []).

%%
%% Interface
%%

store(ChatId, Msg) ->
	gen_server:call(?MODULE, {store, ChatId, Msg}).

check_for_me(ChatId) ->
	gen_server:call(?MODULE, {check_for_me, ChatId}).

%%
%% gen_sever callbacks
%%
init(_Args) ->
	Tab = ets:new(mqueue, [protected, duplicate_bag]),
	{ok, Tab}.

handle_call({store, ChatId, Msg}, _From, Tab) ->
	stats:incr({mqueue, stores}),
	stats:incr({mqueue, length}),
	ets:insert(Tab, {ChatId, Msg}),
	{reply, ok, Tab};

handle_call({check_for_me, ChatId}, _From, Tab) ->
	Entries = ets:lookup(Tab, ChatId),
	ets:delete(Tab, ChatId),
	List = [Msg || {_, Msg} <- Entries],
	stats:decr({mqueue, length}, length(List)),
	{reply, {ok, List}, Tab}.

handle_info(_Msg, State) ->
	{noreply, State}.

handle_cast(_Msg, State) ->
	{noreply, State}.

code_change(_, State, _) ->
	{ok, State}.

terminate(_Reason, _State) ->
	ok.
