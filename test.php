<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin/users', 'GET');
$user = App\Models\User::first();
if ($user) {
    auth()->login($user);
}
$response = $kernel->handle($request);
echo $response->getContent();
if ($response->exception) {
    echo "\n\nEXCEPTION:\n";
    echo $response->exception->getMessage();
}
