

-record(hub_chan, {
		  pieid,
		  sesid
		 }).

-record(hub_req, {
		  pieid,
		  sesid,
		  type,
		  caller,
		  cmd
		 }).
