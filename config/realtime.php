<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Realtime (Socket.IO) — trao đổi bình luận
    |--------------------------------------------------------------------------
    |
    | Bật REALTIME_ENABLED=1 và chạy: npm run realtime
    | Laravel publish Redis → Node Socket.IO → trình duyệt (socket.io-client).
    |
    */

    'enabled' => (bool) env('REALTIME_ENABLED', false),

    'secret' => env('REALTIME_SECRET', env('APP_KEY')),

    'redis_channel' => env('REALTIME_REDIS_CHANNEL', 'va-qlda:realtime'),

    'client_url' => env('REALTIME_CLIENT_URL', 'https://projects.vaschools.edu.vn'),

    'server_port' => (int) env('REALTIME_SERVER_PORT', 6001),

    /*
    | Bật true khi reverse proxy hỗ trợ WebSocket upgrade ổn định.
    | LiteSpeed/OpenLiteSpeed hay lỗi wss + sid — để false, client chỉ long-polling.
    */
    'websocket' => (bool) env('REALTIME_WEBSOCKET', false),

];
