

-record(hub_chan, {
		  pieid,
		  sesid,
		  tabid
		 }).

-record(hub_req, {
		  pieid,
		  sesid,
		  tabid,
		  type,
		  caller,
		  cmd
		 }).
