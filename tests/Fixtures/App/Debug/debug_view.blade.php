<html>
<head>
    <title>Debug View</title>
</head>
<body>
    <h1>Users</h1>
    <ul>
        @foreach ($users as $user)
            <li>{{ $user }}</li>
        @endforeach
    </ul>

    {{ dd($users) }}

    {{ dump($users) }}

    {{ var_dump($users) }}
</body>
</html>
