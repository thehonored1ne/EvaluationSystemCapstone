<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$request = Request::create('/admin/users', 'GET');
$user = User::first();
if ($user) {
    auth()->login($user);
}
$response = $kernel->handle($request);
echo $response->getContent();
if ($response->exception) {
    echo "\n\nEXCEPTION:\n";
    echo $response->exception->getMessage();
}
