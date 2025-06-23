protected $routeMiddleware = [
    'is_admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
];
