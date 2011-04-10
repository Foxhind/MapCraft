-module(config).
-export([get/1]).

%%
%% Interface
%%
get(Par) ->
	{ok, Val} = application:get_env(Par),
	Val.


%%{ docroot, hub_deps:local_path(["priv", "www"])
