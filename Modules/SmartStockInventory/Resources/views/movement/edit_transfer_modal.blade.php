<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Stock Transfer</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #f6f8fb;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        }
        .wrap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .head {
            padding: 10px 14px;
            border-bottom: 1px solid #e5e9f2;
            background: #fff;
            font-size: 14px;
            color: #24334a;
            font-weight: 600;
        }
        .frame-holder {
            flex: 1;
            min-height: 700px;
            background: #fff;
        }
        .frame-holder iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">Edit Stock Transfer #{{ $tx->id }}</div>
    <div class="frame-holder">
        <iframe src="{{ $coreEditUrl }}" title="Edit Stock Transfer"></iframe>
    </div>
</div>
</body>
</html>

