%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.


-module(api).
-compile(export_all).


parse_line(Line) ->
	Parts = re:split(Line, "!json:", [{return, list}]),
	case Parts of
		[Cmd, Json] ->
			split_cmd(Cmd) ++ [{json, Json}];
		[Cmd] ->
			split_cmd(Cmd)
		end.

split_cmd(Cmd) ->
	string:tokens(Cmd, "!").


format_line(CmdParts) ->
	format_line(CmdParts, []).

format_line([{json, Head} | Rest], Acc) ->
	Part = "json:" ++ Head,
	format_line(Rest, ["!", Part | Acc]);

format_line([Atom | Rest], Acc) when is_atom(Atom)->
	Head = atom_to_list(Atom),
	format_line(Rest, ["!", Head | Acc]);

format_line([Head | Rest], Acc) ->
	format_line(Rest, ["!", Head | Acc]);

format_line([], ["!" | Rest]) ->
	List = lists:flatten(lists:reverse(Rest)) ++ "\n",
	list_to_binary(List).



%%
%% Tests
%%
-ifdef(TEST).
-include_lib("eunit/include/eunit.hrl").


tests() ->
	[ {"a!b",  ["a", "b"]},
	  {"a", ["a"]},
	  {"a!b!json:c", ["a", "b", {json, "c"}]} ].

parse_test_() ->
	[ ?_assert(Parts =:= parse_line(Cmd)) || {Cmd, Parts} <- tests()].

format_test_() ->
	[ ?_assert(Cmd ++ "\n" =:= binary_to_list(format_line(Parts))) || {Cmd, Parts} <- tests()].

format_add_test_() ->
	[ ?_assert( <<"a!b!c\n">> =:= format_line(["a", b, "c"]) ),
	  ?_assert( <<"a!json:b\n">> =:= format_line([a, {json, "b"}]) )
	].

-endif.

