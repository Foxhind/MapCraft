

-record(hub_chan, {
		  pieid,
		  sesid,
		  tabid
		 }).

-record(hub_req, {
		  pieid,
		  sesid,
		  type,
		  caller,
		  cmd
		 }).
