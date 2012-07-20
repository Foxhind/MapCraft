%% This program is free software. It comes without any warranty, to
%% the extent permitted by applicable law. You can redistribute it
%% and/or modify it under the terms of the Do What The Fuck You Want
%% To Public License, Version 2, as published by Sam Hocevar. See
%% http://sam.zoy.org/wtfpl/COPYING for more details.

-module(config).
-export([get/1]).

%%
%% Interface
%%
get(Par) ->
	{ok, Val} = application:get_env(Par),
	Val.


%%{ docroot, hub_deps:local_path(["priv", "www"])
