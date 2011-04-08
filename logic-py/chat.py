import api, hub, validators, dispatcher from mapcraft

@dispatcher.register('msg')
def send_chat_msg (cmd, type, caller, data):
	validators.require(data, ['message'])
	msg = api.chat_msg(frm=caller, message=data['message'])

	res = []
	if data['type'] == 'public':
		res.append(hub.to_pie(msg))
	else:
		validators.require(data, ['target_nick'])
		to = find_session_by_nick(caller, data['target_nick'])

		res.append(hub.to_session(caller, msg))
		res.append(hub.to_session(to, msg))

	return res
