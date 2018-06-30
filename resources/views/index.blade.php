<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Socket Client Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>WebSocket Client</h1>
<h3 id="login_msg"></h3>
<div id="content"></div>
<script>
    var user_id = '{{$user_id}}';
    var wsServer = 'ws://127.0.0.1:9501';
    var socket = new WebSocket(wsServer);
    socket.onopen = function (evt) {
        if (socket.readyState == 1) {
            var data = '{"cmd":"login","datas":{"uid":"' + user_id + '"}}';
            socket.send(data);
            document.getElementById('login_msg').innerHTML = 'user login: ' + user_id;
        } else {
            alert('Connection failed');
        }
    };

    socket.onmessage = function (evt) {
        document.getElementById('content').innerHTML += evt.data;
    };


</script>
</body>
</html>